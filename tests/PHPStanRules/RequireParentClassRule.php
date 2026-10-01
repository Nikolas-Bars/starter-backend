<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\ClassLike>
 */
final class RequireParentClassRule implements Rule
{
    /**
     * Классы, которые могут не иметь родителей
     */
    private const ALLOWED_WITHOUT_PARENT = [
        // Базовые классы проекта
        'BaseAction',
        'BaseController',
        'BaseRepository',
        'BaseService',
        'BaseTask',
        'BaseHandler',
        'BaseJob',
        'BaseEvent',
        'BaseListener',
        'BaseRequest',
        'BaseResource',
        'BaseMiddleware',
        'BaseException',
        'BasePolicy',
        'BaseRule',
        'BaseValidator',
        'ApiException',
    ];

    /**
     * Типы классов, которые должны иметь родителей
     */
    private const FOLDER_PARENT_REQUIREMENTS = [
        'Actions'      => ['BaseAction'],
        'Controllers'  => ['BaseController', 'Controller'],
        'Repositories' => ['BaseRepository', 'Repository'],
        'Services'     => ['BaseService', 'Service'],
        'Tasks'        => ['BaseTask', 'Task'],
        'Handlers'     => ['BaseHandler', 'Handler'],
        'Jobs'         => ['BaseJob', 'Job'],
        'Events'       => ['BaseEvent', 'Event'],
        'Listeners'    => ['BaseListener', 'Listener'],
        'Requests'     => ['BaseRequest', 'Request', 'FormRequest'],
        'Resources'    => ['BaseResource', 'Resource', 'JsonResource'],
        'Middlewares'  => ['BaseMiddleware', 'Middleware'],
        'Exceptions'   => ['BaseException', 'Exception', 'ApiException'],
        'Policies'     => ['BasePolicy', 'Policy'],
        'Rules'        => ['BaseRule', 'Rule'],
        'Validators'   => ['BaseValidator', 'Validator'],
        'DTO'          => ['Data'],
    ];

    public function getNodeType(): string
    {
        return Node\Stmt\ClassLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $fileName = $scope->getFile();

        // Проверяем только файлы в папке Modules
        if (!\str_contains($fileName, '/Modules/') && !\str_contains($fileName, '/modules/')) {
            return [];
        }

        // Пропускаем анонимные классы, интерфейсы и трейты
        if ($node->name === null || $node instanceof Node\Stmt\Interface_ || $node instanceof Node\Stmt\Trait_) {
            return [];
        }

        // Пропускаем enums - они не могут наследоваться
        if ($node instanceof Node\Stmt\Enum_) {
            return [];
        }

        $className = $node->name->toString();

        // Пропускаем базовые классы проекта
        if (\in_array($className, self::ALLOWED_WITHOUT_PARENT, true)) {
            return [];
        }

        // Парсим путь к файлу
        $pathParts = $this->parseModulePath($fileName);
        if ($pathParts === null) {
            return [];
        }

        $folder = $pathParts['folder'];

        // Проверяем только классы в определенных папках
        if ($folder === null || !isset(self::FOLDER_PARENT_REQUIREMENTS[$folder])) {
            return [];
        }

        $errors = [];

        // Проверяем, что класс наследуется
        if (!$node instanceof Node\Stmt\Class_ || $node->extends === null) {
            $errors[] = RuleErrorBuilder::message(
                \sprintf(
                    'Class "%s" in folder "%s" must extend a parent class',
                    $className,
                    $folder,
                ),
            )
                ->line($node->getLine())
                ->identifier('class.inheritance.missing')
                ->build();

            return $errors;
        }

        // Получаем имя родительского класса
        $parentClassName = $this->getParentClassName($node->extends);
        $allowedParents  = self::FOLDER_PARENT_REQUIREMENTS[$folder];

        // Проверяем, что родительский класс соответствует требованиям
        $isValidParent = false;
        foreach ($allowedParents as $allowedParent) {
            if (\str_contains($parentClassName, $allowedParent)) {
                $isValidParent = true;
                break;
            }
        }

        if (!$isValidParent) {
            $errors[] = RuleErrorBuilder::message(
                \sprintf(
                    'Class "%s" in folder "%s" must extend one of: %s. Currently extends: %s',
                    $className,
                    $folder,
                    \implode(', ', $allowedParents),
                    $parentClassName,
                ),
            )
                ->line($node->getLine())
                ->identifier('class.inheritance.invalid')
                ->build();
        }

        return $errors;
    }

    /**
     * @return array{module: string, folder: string|null}|null
     */
    private function parseModulePath(string $fileName): ?array
    {
        // Ищем путь вида: .../Modules/ModuleName/Folder/...
        $matchResult = \preg_match('#/Modules/([^/]+)(?:/([^/]+))?/#i', $fileName, $matches);
        if ($matchResult === 1) {
            return [
                'module' => $matches[1],
                'folder' => $matches[2] ?? null,
            ];
        }

        return null;
    }

    private function getParentClassName(Node\Name $parentName): string
    {
        if ($parentName instanceof Node\Name\FullyQualified) {
            // Полное имя класса
            return $parentName->toString();
        }

        // Простое имя класса
        return $parentName->getLast();
    }
}
