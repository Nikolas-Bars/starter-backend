<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\ClassLike>
 */
final class ModuleNamingConventionRule implements Rule
{
    private const SINGULAR_FOLDERS = ['DTO', 'Http', 'Database', 'Console', 'Search'];

    private const FOLDER_SUFFIXES = [
        'Actions'      => 'Action',
        'SubActions'   => 'SubAction',
        'DTO'          => 'DTO',
        'Controllers'  => 'Controller',
        'Repositories' => 'Repository',
        'Services'     => 'Service',
        'Tasks'        => 'Task',
        'Handlers'     => 'Handler',
        'Jobs'         => 'Job',
        //        'Events'       => 'Event',
        'Listeners'   => 'Listener',
        'Requests'    => 'Request',
        'Resources'   => 'Resource',
        'Middlewares' => 'Middleware',
        'Exceptions'  => 'Exception',
        'Policies'    => 'Policy',
        'Rules'       => 'Rule',
        'Validators'  => 'Validator',
        'Enums'       => 'Enum',
    ];

    public function getNodeType(): string
    {
        return Node\Stmt\ClassLike::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $fileName = $scope->getFile();

        // Проверяем только файлы в папке Modules
        if (!\str_contains($fileName, '/Modules/') && !\str_contains($fileName, '/modules/')) {
            return [];
        }

        // Пропускаем анонимные классы/enums
        if ($node->name === null) {
            return [];
        }

        $className = $node->name->toString();
        $errors    = [];

        // Парсим путь к файлу
        $pathParts = $this->parseModulePath($fileName);
        if ($pathParts === null) {
            return [];
        }

        // Проверка 1: Название модуля должно быть в единственном числе
        if ($this->isPlural($pathParts['module'])) {
            $errors[] = RuleErrorBuilder::message(
                \sprintf('Module name "%s" should be in singular form', $pathParts['module']),
            )
                ->line($node->getLine())
                ->identifier('module.naming.singular')
                ->build();
        }

        // Проверка 2: Папки внутри модуля должны быть во множественном числе (кроме исключений)
        $folder = $pathParts['folder'];
        if ($folder !== null && !\in_array($folder, self::SINGULAR_FOLDERS, true)) {
            if (!$this->isPlural($folder)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf('Folder "%s" should be in plural form', $folder),
                )
                    ->line($node->getLine())
                    ->identifier('module.folder.naming.plural')
                    ->build();
            }
        }

        // Проверка 3: Классы и enums должны иметь соответствующие суффиксы
        if ($folder !== null && isset(self::FOLDER_SUFFIXES[$folder])) {
            $expectedSuffix = self::FOLDER_SUFFIXES[$folder];
            if (!\str_ends_with($className, $expectedSuffix)) {
                $nodeType = $node instanceof Node\Stmt\Enum_ ? 'Enum' : 'Class';
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        '%s "%s" in folder "%s" should have suffix "%s"',
                        $nodeType,
                        $className,
                        $folder,
                        $expectedSuffix,
                    ),
                )
                    ->line($node->getLine())
                    ->identifier('module.class.naming.suffix')
                    ->build();
            }
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

    private function isPlural(string $word): bool
    {
        // Простая проверка на множественное число
        // Можно расширить для более сложных случаев

        // Исключения для неправильных множественных форм
        $irregularPlurals = [
            'children',
            'people',
            'men',
            'women',
            'feet',
            'teeth',
            'mice',
            'geese',
        ];

        if (\in_array(\strtolower($word), $irregularPlurals, true)) {
            return true;
        }

        // Основные правила множественного числа в английском
        return \str_ends_with($word, 's') ||
               \str_ends_with($word, 'es') ||
               \str_ends_with($word, 'ies') ||
               \str_ends_with($word, 'ves');
    }
}
