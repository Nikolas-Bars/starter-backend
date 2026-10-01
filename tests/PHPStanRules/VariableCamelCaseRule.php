<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Variable>
 */
final class VariableCamelCaseRule implements Rule
{
    public function getNodeType(): string
    {
        return Variable::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Убираем избыточную проверку instanceof, так как тип уже гарантирован через getNodeType()
        // Пропускаем переменные без имени или с динамическими именами
        if (!\is_string($node->name)) {
            return [];
        }

        $variableName = $node->name;

        // Пропускаем суперглобальные переменные и специальные переменные PHP
        $skipVariables = [
            '_GET',
            '_POST',
            '_SERVER',
            '_SESSION',
            '_COOKIE',
            '_FILES',
            '_ENV',
            'GLOBALS',
            'argc',
            'argv',
            'this',
        ];

        if (\in_array($variableName, $skipVariables, true)) {
            return [];
        }

        // Пропускаем переменные, которые начинаются с подчеркивания (часто используются для приватных/защищенных)
        if (\str_starts_with($variableName, '_')) {
            return [];
        }

        // Проверяем соответствие camelCase
        if (!$this->isCamelCase($variableName)) {
            return [
                RuleErrorBuilder::message(
                    \sprintf(
                        'Variable $%s is not in camelCase format. Expected format: $%s',
                        $variableName,
                        $this->toCamelCase($variableName),
                    ),
                )
                    ->line($node->getLine())
                    ->identifier('variable.camelCase') // Добавляем идентификатор для ошибки
                    ->build(),
            ];
        }

        return [];
    }

    private function isCamelCase(string $name): bool
    {
        // camelCase должен начинаться с маленькой буквы
        // и не содержать подчеркиваний или дефисов
        return \preg_match('/^[a-z][a-zA-Z0-9]*$/', $name) === 1;
    }

    private function toCamelCase(string $name): string
    {
        // Преобразуем snake_case в camelCase для примера в сообщении об ошибке
        $parts     = \explode('_', \strtolower($name));
        $camelCase = \array_shift($parts);

        foreach ($parts as $part) {
            $camelCase .= \ucfirst($part);
        }

        return $camelCase;
    }
}
