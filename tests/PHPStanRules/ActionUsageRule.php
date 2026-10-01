<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Action Usage Rule
 *
 * Проверяет правильное использование Actions в архитектуре Porto:
 * - Actions не должны вызывать другие Actions напрямую (исключение: SubAction)
 * - Actions могут вызывать SubAction
 * - SubAction не может вызывать обычные Action
 * - Tasks не должны вызывать Actions
 * - SubAction не должны вызывать другие SubAction
 *
 * @implements Rule<Node>
 */
class ActionUsageRule implements Rule
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

        // Проверяем методы вызовов
        if ($node instanceof MethodCall) {
            $errors = \array_merge($errors, $this->checkMethodCall($node, $scope));
        }

        // Проверяем статические вызовы
        if ($node instanceof StaticCall) {
            $errors = \array_merge($errors, $this->checkStaticCall($node, $scope));
        }

        return $errors;
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
            $currentClass = $scope->getClassReflection()?->getName();

            if ($this->isActionClass($className)) {
                if ($this->isInRegularAction($scope)) {
                    // Обычный Action может вызывать только SubAction или методы своего же класса
                    if (!$this->isSubActionClass($className) && $className !== $currentClass) {
                        $errors[] = RuleErrorBuilder::message(
                            \sprintf(
                                'Action не должен вызывать другой Action %s напрямую. Используйте SubAction, Task или создайте общий Task.',
                                $className,
                            ),
                        )->line($node->getLine())->identifier('action.action')->build();
                    }
                } elseif ($this->isInSubAction($scope)) {
                    // SubAction не может вызывать обычные Action
                    if (!$this->isSubActionClass($className)) {
                        $errors[] = RuleErrorBuilder::message(
                            \sprintf(
                                'SubAction не должен вызывать обычный Action %s. Используйте Task вместо этого.',
                                $className,
                            ),
                        )->line($node->getLine())->identifier('subaction.action')->build();
                    }
                    // SubAction не должен вызывать другие SubAction
                    elseif ($this->isSubActionClass($className) && $className !== $currentClass) {
                        $errors[] = RuleErrorBuilder::message(
                            \sprintf(
                                'SubAction не должен вызывать другой SubAction %s. Используйте Task вместо этого.',
                                $className,
                            ),
                        )->line($node->getLine())->identifier('subaction.subaction')->build();
                    }
                } elseif ($this->isInTask($scope)) {
                    $errors[] = RuleErrorBuilder::message(
                        \sprintf(
                            'Task не должен вызывать Action %s. Tasks должны быть вызваны из Actions.',
                            $className,
                        ),
                    )->line($node->getLine())->identifier('task.action')->build();
                }
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

            $currentClass = $scope->getClassReflection()?->getName();

            if ($this->isActionClass($className)) {
                if ($this->isInRegularAction($scope)) {
                    // Обычный Action может вызывать статические методы только SubAction или своего же класса
                    if (!$this->isSubActionClass($className) && $className !== $currentClass) {
                        $errors[] = RuleErrorBuilder::message(
                            \sprintf(
                                'Action не должен вызывать статические методы другого Action %s. Используйте SubAction, Task или создайте общий Task.',
                                $className,
                            ),
                        )->line($node->getLine())->identifier('action.action.static')->build();
                    }
                } elseif ($this->isInSubAction($scope)) {
                    // SubAction не может вызывать статические методы обычных Action
                    if (!$this->isSubActionClass($className)) {
                        $errors[] = RuleErrorBuilder::message(
                            \sprintf(
                                'SubAction не должен вызывать статические методы обычного Action %s. Используйте Task вместо этого.',
                                $className,
                            ),
                        )->line($node->getLine())->identifier('subaction.action.static')->build();
                    }
                    // SubAction не должен вызывать статические методы других SubAction
                    elseif ($this->isSubActionClass($className) && $className !== $currentClass) {
                        $errors[] = RuleErrorBuilder::message(
                            \sprintf(
                                'SubAction не должен вызывать статические методы другого SubAction %s. Используйте Task вместо этого.',
                                $className,
                            ),
                        )->line($node->getLine())->identifier('subaction.subaction.static')->build();
                    }
                } elseif ($this->isInTask($scope)) {
                    $errors[] = RuleErrorBuilder::message(
                        \sprintf(
                            'Task не должен вызывать статические методы Action %s. Tasks должны быть вызваны из Actions.',
                            $className,
                        ),
                    )->line($node->getLine())->identifier('task.action.static')->build();
                }
            }
        }

        return $errors;
    }

    private function isInRegularAction(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return false;
        }

        $className = $classReflection->getName();

        // Проверяем что это Action, но не SubAction
        return ($this->isActionClass($className) && !$this->isSubActionClass($className));
    }

    private function isInSubAction(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return false;
        }

        $className = $classReflection->getName();

        return $this->isSubActionClass($className);
    }

    private function isInTask(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return false;
        }

        $className = $classReflection->getName();

        return \str_contains($className, '\\Tasks\\') ||
               \str_ends_with($className, 'Task');
    }

    private function isActionClass(string $className): bool
    {
        return \str_contains($className, '\\Actions\\') ||
               \str_ends_with($className, 'Action');
    }

    private function isSubActionClass(string $className): bool
    {
        return \str_contains($className, '\\Actions\\') &&
               \str_ends_with($className, 'SubAction');
    }
}
