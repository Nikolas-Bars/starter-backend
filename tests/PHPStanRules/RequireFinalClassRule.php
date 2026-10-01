<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Class_>
 */
final class RequireFinalClassRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Пропускаем абстрактные классы - они не могут быть final
        if ($node->isAbstract()) {
            return [];
        }

        // Пропускаем уже final классы
        if ($node->isFinal()) {
            return [];
        }

        // Пропускаем анонимные классы
        if ($node->name === null) {
            return [];
        }

        $className = $node->name->toString();
        $fileName  = $scope->getFile();

        // Исключаем ВСЕ миграции (приоритет над правилом модулей)
        if ($this->isMigrationClass($className, $fileName)) {
            return [];
        }

        // Если класс в папке modules - он должен быть final обязательно
        if (\str_contains($fileName, '/Modules/') || \str_contains($fileName, '/modules/')) {
            return [
                RuleErrorBuilder::message(
                    \sprintf('Class "%s" in modules directory must be declared as final', $className),
                )
                    ->line($node->getLine())
                    ->identifier('class.final.modules.required')
                    ->build(),
            ];
        }

        // Исключения для определенных типов классов (только для классов НЕ в modules)
        if ($this->isExcludedClass($className, $scope)) {
            return [];
        }

        // Проверяем, есть ли наследуемые методы или свойства
        if ($this->hasInheritableMembers($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                \sprintf('Class "%s" should be declared as final if it is not intended for inheritance', $className),
            )
                ->line($node->getLine())
                ->identifier('class.final.recommended')
                ->build(),
        ];
    }

    private function isMigrationClass(string $className, string $fileName): bool
    {
        // Проверяем по пути к файлу
        if (\str_contains($fileName, '/database/migrations/') ||
            \str_contains($fileName, '/Database/Migrations/')) {
            return true;
        }

        // Проверяем по названию класса
        if (\str_contains($className, 'Migration')) {
            return true;
        }

        return false;
    }

    private function isExcludedClass(string $className, Scope $scope): bool
    {
        $fileName = $scope->getFile();

        // Исключаем тестовые классы
        if (\str_contains($fileName, '/tests/') || \str_contains($fileName, '/Tests/')) {
            return true;
        }

        // Исключаем конфигурационные классы
        if (\str_contains($fileName, '/config/')) {
            return true;
        }

        // Исключаем определенные паттерны классов
        $excludedPatterns = [
            'Controller',
            'Middleware',
            'Model',
            'Seeder',
            'Factory',
            'Provider',
            'Kernel',
            'Handler',
            'Command',
            'Job',
            'Event',
            'Listener',
            'Mail',
            'Notification',
            'Request',
            'Resource',
            'Policy',
            'Gate',
        ];

        foreach ($excludedPatterns as $pattern) {
            if (\str_contains($className, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function hasInheritableMembers(Node\Stmt\Class_ $node): bool
    {
        foreach ($node->stmts as $stmt) {
            // Проверяем методы
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                // Если метод не private и не final, класс может быть наследован
                if (!$stmt->isPrivate() && !$stmt->isFinal()) {
                    // Исключаем конструкторы - они обычно наследуются
                    if ($stmt->name->toString() !== '__construct') {
                        return true;
                    }
                }
            }

            // Проверяем свойства
            if ($stmt instanceof Node\Stmt\Property) {
                // Если свойство не private, класс может быть наследован
                if (!$stmt->isPrivate()) {
                    return true;
                }
            }
        }

        return false;
    }
}
