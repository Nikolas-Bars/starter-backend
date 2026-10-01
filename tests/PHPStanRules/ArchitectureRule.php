<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Architecture Rule
 *
 * Проверяет соблюдение архитектурных принципов:
 * - Контроллеры не должны напрямую обращаться к репозиториям
 * - Контроллеры должны использовать только Actions
 * - Actions могут использовать Tasks и Repositories
 * - Tasks могут использовать Repositories и другие Tasks
 *
 * @implements Rule<Node>
 */
class ArchitectureRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @param  Node                      $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Проверяем только если находимся в контроллере
        if (!$this->isInController($scope)) {
            return [];
        }

        // Проверяем методы вызовов
        if ($node instanceof MethodCall) {
            $errors = \array_merge($errors, $this->checkMethodCall($node, $scope));
        }

        // Проверяем статические вызовы
        if ($node instanceof StaticCall) {
            $errors = \array_merge($errors, $this->checkStaticCall($node, $scope));
        }

        // Проверяем создание новых объектов
        if ($node instanceof New_) {
            $errors = \array_merge($errors, $this->checkNewInstance($node, $scope));
        }

        return $errors;
    }

    private function isInController(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return false;
        }

        $className = $classReflection->getName();

        // Проверяем что класс находится в namespace контроллеров
        return \str_contains($className, '\\Http\\Controllers\\') ||
               \str_ends_with($className, 'Controller');
    }

    /**
     * @param  MethodCall                $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    private function checkMethodCall(MethodCall $node, Scope $scope): array
    {
        $errors = [];

        // Получаем тип объекта на котором вызывается метод
        $callerType = $scope->getType($node->var);

        foreach ($callerType->getObjectClassNames() as $className) {
            if ($this->isRepositoryClass($className)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Контроллер не должен напрямую обращаться к репозиторию %s. Используйте Action вместо этого.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('controller.repository')->build();
            }

            if ($this->isTaskClass($className)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Контроллер не должен напрямую обращаться к Task %s. Используйте Action вместо этого.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('controller.task')->build();
            }

            if ($this->isModelClass($className) && $this->isDataManipulationMethod($node)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Контроллер не должен напрямую манипулировать данными модели %s. Используйте Action вместо этого.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('controller.model')->build();
            }
        }

        return $errors;
    }

    /**
     * @param  StaticCall                $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    private function checkStaticCall(StaticCall $node, Scope $scope): array
    {
        $errors = [];

        if ($node->class instanceof Name) {
            $className = $node->class->toString();

            // Разрешаем полное имя класса
            if (!\str_contains($className, '\\')) {
                $nameScope = $scope->getNamespace();
                if ($nameScope !== null) {
                    $className = $nameScope . '\\' . $className;
                }
            }

            if ($this->isRepositoryClass($className)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Контроллер не должен напрямую вызывать статические методы репозитория %s. Используйте Action вместо этого.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('controller.repository.static')->build();
            }

            if ($this->isModelClass($className) && $this->isDataManipulationStaticMethod($node)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Контроллер не должен напрямую вызывать статические методы модели %s для манипуляции данными. Используйте Action вместо этого.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('controller.model.static')->build();
            }
        }

        return $errors;
    }

    /**
     * @param  New_                      $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    private function checkNewInstance(New_ $node, Scope $scope): array
    {
        $errors = [];

        if ($node->class instanceof Name) {
            $className = $node->class->toString();

            // Разрешаем полное имя класса
            if (!\str_contains($className, '\\')) {
                $nameScope = $scope->getNamespace();
                if ($nameScope !== null) {
                    $className = $nameScope . '\\' . $className;
                }
            }

            if ($this->isRepositoryClass($className)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Контроллер не должен напрямую создавать экземпляр репозитория %s. Используйте Action вместо этого.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('controller.repository.new')->build();
            }

            if ($this->isTaskClass($className)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Контроллер не должен напрямую создавать экземпляр Task %s. Используйте Action вместо этого.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('controller.task.new')->build();
            }
        }

        return $errors;
    }

    private function isRepositoryClass(string $className): bool
    {
        return \str_contains($className, '\\Repositories\\') ||
               \str_ends_with($className, 'Repository');
    }

    private function isTaskClass(string $className): bool
    {
        return \str_contains($className, '\\Tasks\\') ||
               \str_ends_with($className, 'Task');
    }

    private function isModelClass(string $className): bool
    {
        return \str_contains($className, '\\Models\\') ||
               \str_contains($className, 'Illuminate\\Database\\Eloquent\\Model');
    }

    private function isDataManipulationMethod(MethodCall $node): bool
    {
        if (!$node->name instanceof Node\Identifier) {
            return false;
        }

        $methodName = $node->name->toString();

        $dataManipulationMethods = [
            'save', 'create', 'update', 'delete', 'destroy', 'forceDelete',
            'restore', 'increment', 'decrement', 'touch', 'insert', 'updateOrInsert',
        ];

        return \in_array($methodName, $dataManipulationMethods, true);
    }

    private function isDataManipulationStaticMethod(StaticCall $node): bool
    {
        if (!$node->name instanceof Node\Identifier) {
            return false;
        }

        $methodName = $node->name->toString();

        $dataManipulationMethods = [
            'create', 'insert', 'insertOrIgnore', 'insertGetId', 'insertUsing',
            'update', 'updateOrInsert', 'upsert', 'delete', 'destroy', 'truncate',
        ];

        return \in_array($methodName, $dataManipulationMethods, true);
    }
}
