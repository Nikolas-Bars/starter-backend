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
 * Repository Usage Rule
 *
 * Репозитории — слой доступа к данным, обращаться к ним можно только из:
 * - Action (в т.ч. SubAction)
 * - Task
 * - другого Repository (композиция репозиториев)
 *
 * Любые другие слои (Controllers, Requests, Resources, Jobs, Listeners и т.д.)
 * не должны использовать репозитории напрямую — им следует вызывать Task или Action.
 *
 * @implements Rule<Node>
 */
class RepositoryUsageRule implements Rule
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
        // В разрешённых слоях (Action/Task/Repository) обращение к репозиторию допустимо
        if ($this->isInAllowedContext($scope)) {
            return [];
        }

        if ($node instanceof MethodCall) {
            return $this->checkMethodCall($node, $scope);
        }

        if ($node instanceof StaticCall) {
            return $this->checkStaticCall($node, $scope);
        }

        if ($node instanceof New_) {
            return $this->checkNewInstance($node, $scope);
        }

        return [];
    }

    /**
     * @param  MethodCall                $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    private function checkMethodCall(MethodCall $node, Scope $scope): array
    {
        $errors = [];

        $callerType = $scope->getType($node->var);

        foreach ($callerType->getObjectClassNames() as $className) {
            if ($this->isRepositoryClass($className)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf(
                        'Запрещено обращаться к репозиторию %s из этого слоя. Репозиторий должен использоваться только из Action или Task.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('repository.usage')->build();
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
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $this->resolveClassName($node->class->toString(), $scope);

        if ($this->isRepositoryClass($className)) {
            return [
                RuleErrorBuilder::message(
                    \sprintf(
                        'Запрещено вызывать статические методы репозитория %s из этого слоя. Репозиторий должен использоваться только из Action или Task.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('repository.usage.static')->build(),
            ];
        }

        return [];
    }

    /**
     * @param  New_                      $node
     * @param  Scope                     $scope
     * @return list<IdentifierRuleError>
     */
    private function checkNewInstance(New_ $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $this->resolveClassName($node->class->toString(), $scope);

        if ($this->isRepositoryClass($className)) {
            return [
                RuleErrorBuilder::message(
                    \sprintf(
                        'Запрещено создавать экземпляр репозитория %s в этом слое. Репозиторий должен использоваться только из Action или Task.',
                        $className,
                    ),
                )->line($node->getLine())->identifier('repository.usage.new')->build(),
            ];
        }

        return [];
    }

    private function resolveClassName(string $className, Scope $scope): string
    {
        if (!\str_contains($className, '\\')) {
            $namespace = $scope->getNamespace();
            if ($namespace !== null) {
                return $namespace . '\\' . $className;
            }
        }

        return $className;
    }

    private function isInAllowedContext(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return false;
        }

        $className = $classReflection->getName();

        return $this->isActionClass($className)
            || $this->isTaskClass($className)
            || $this->isRepositoryClass($className);
    }

    private function isActionClass(string $className): bool
    {
        return \str_contains($className, '\\Actions\\') ||
               \str_ends_with($className, 'Action');
    }

    private function isTaskClass(string $className): bool
    {
        return \str_contains($className, '\\Tasks\\') ||
               \str_ends_with($className, 'Task');
    }

    private function isRepositoryClass(string $className): bool
    {
        // Только доменные репозитории приложения (App\Modules\*\Repositories\*),
        // чтобы не задевать фреймворковые классы вроде Illuminate\Config\Repository.
        return \str_starts_with($className, 'App\\') &&
               \str_contains($className, '\\Repositories\\');
    }
}
