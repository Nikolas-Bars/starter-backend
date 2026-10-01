<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Use_>
 */
final class UnusedUseRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Use_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Получаем все содержимое файла
        $fileContent = \file_get_contents($scope->getFile());
        if ($fileContent === false) {
            return [];
        }

        foreach ($node->uses as $use) {
            $fullName = $use->name->toString();
            $alias    = $use->alias !== null ? $use->alias->toString() : null;

            // Определяем какое имя используется в коде (алиас или последняя часть namespace)
            $usedName = $alias !== null ? $alias : $this->getShortName($fullName);

            // Проверяем, используется ли класс/интерфейс/трейт в коде
            if (!$this->isUsedInCode($fileContent, $usedName, $fullName)) {
                $errors[] = RuleErrorBuilder::message(
                    \sprintf('Unused use statement "%s"', $fullName),
                )
                    ->line($use->getLine())
                    ->identifier('unused.use')
                    ->build();
            }
        }

        return $errors;
    }

    private function getShortName(string $fullName): string
    {
        $parts    = \explode('\\', $fullName);
        $lastPart = \end($parts);

        return $lastPart !== '' ? $lastPart : $fullName;
    }

    private function isUsedInCode(string $fileContent, string $usedName, string $fullName): bool
    {
        // Удаляем use statements из проверки (чтобы не считать сам import за использование)
        $contentWithoutUse = \preg_replace('/^use\s+[^;]+;/m', '', $fileContent);
        if ($contentWithoutUse === null) {
            $contentWithoutUse = $fileContent;
        }

        // Простая проверка на наличие имени класса в коде
        $simplePattern = '/\b' . \preg_quote($usedName, '/') . '\b/';
        if (\preg_match($simplePattern, $contentWithoutUse) === 1) {
            return true;
        }

        // Дополнительные специфичные паттерны
        $patterns = [
            // Типизация параметров и возвращаемых значений
            '/:\s*' . \preg_quote($usedName, '/') . '\b/',
            // Аннотации в комментариях (PHPDoc)
            '/@(?:param|return|var|throws|property)\s+[^*\r\n]*\b' . \preg_quote($usedName, '/') . '\b/',
            // Использование в массивах конфигурации
            '/[\'"]' . \preg_quote($fullName, '/') . '[\'"]/',
            // Использование через ::class
            '/\b' . \preg_quote($usedName, '/') . '::class\b/',
        ];

        foreach ($patterns as $pattern) {
            if (\preg_match($pattern, $fileContent) === 1) {
                return true;
            }
        }

        return false;
    }
}
