<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Stmt\Namespace_>
 */
final class RequireStrictTypesRule implements Rule
{
    /**
     * @var array<string, bool>
     */
    private array $checkedFiles = [];

    public function getNodeType(): string
    {
        return Node\Stmt\Namespace_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $fileName = $scope->getFile();

        // Избегаем повторной проверки одного файла
        if (isset($this->checkedFiles[$fileName])) {
            return [];
        }

        $this->checkedFiles[$fileName] = true;

        // Исключаем определенные типы файлов
        if (\str_contains($fileName, 'test') ||
            \str_contains($fileName, 'Test') ||
            \str_contains($fileName, 'config/') ||
            \str_contains($fileName, 'database/migrations/') ||
            \str_contains($fileName, '.blade.php')) {
            return [];
        }

        $fileContent = \file_get_contents($fileName);

        if ($fileContent === false) {
            return [];
        }

        // Проверяем наличие declare(strict_types=1), но не закомментированного
        $hasValidDeclare = \preg_match('/^(?!.*\/\/.*declare).*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m', $fileContent) === 1;

        if (!$hasValidDeclare) {
            return [
                RuleErrorBuilder::message('File must contain "declare(strict_types=1);" declaration (not commented out)')
                    ->line(1)
                    ->identifier('strictTypes.missing') // Добавляем идентификатор
                    ->build(),
            ];
        }

        return [];
    }
}
