<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Class_>
 */
final class ControllerInvokeOnlyRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Пропускаем анонимные классы
        if ($node->name === null) {
            return [];
        }

        $className = $node->name->toString();

        // Проверяем только контроллеры
        if (!\str_ends_with($className, 'Controller')) {
            return [];
        }

        $fileName = $scope->getFile();

        // Проверяем, что это контроллер в папке Modules
        if (!\str_contains($fileName, '/Modules/') && !\str_contains($fileName, '/modules/')) {
            return [];
        }

        // Дополнительная проверка, что это действительно контроллер в модуле
        if (!\str_contains($fileName, '/Controllers/') && !\str_contains($fileName, '/controllers/')) {
            return [];
        }

        $errors          = [];
        $hasInvokeMethod = false;
        $publicMethods   = [];

        // Проходим по всем методам класса
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Node\Stmt\ClassMethod) {
                continue;
            }

            // Пропускаем приватные и защищенные методы
            if ($stmt->isPrivate() || $stmt->isProtected()) {
                continue;
            }

            // Пропускаем конструктор
            if ($stmt->name->toString() === '__construct') {
                continue;
            }

            $methodName = $stmt->name->toString();

            if ($methodName === '__invoke') {
                $hasInvokeMethod = true;
            } else {
                $publicMethods[] = $methodName;
            }
        }

        // Проверка 1: Контроллер должен иметь метод __invoke
        if (!$hasInvokeMethod) {
            $errors[] = RuleErrorBuilder::message(
                \sprintf('Controller "%s" in Modules must have an __invoke method', $className),
            )
                ->line($node->getLine())
                ->identifier('controller.invoke.missing')
                ->build();
        }

        // Проверка 2: Контроллер не должен иметь других публичных методов кроме __invoke
        if (\count($publicMethods) > 0) {
            $errors[] = RuleErrorBuilder::message(
                \sprintf(
                    'Controller "%s" in Modules should only have __invoke method. Found public methods: %s',
                    $className,
                    \implode(', ', $publicMethods),
                ),
            )
                ->line($node->getLine())
                ->identifier('controller.invoke.only')
                ->build();
        }

        return $errors;
    }
}
