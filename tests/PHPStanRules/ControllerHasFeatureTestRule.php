<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Каждый контроллер модуля должен иметь feature-тест.
 *
 * Тест ищется в `app/Modules/{Module}/Tests/Feature/` по двум вариантам имени:
 *   - {ControllerName}Test.php                  (например, CreateGameControllerTest.php)
 *   - {ControllerName без суффикса Controller}Test.php  (например, ListGameTeamsTest.php)
 *
 * @implements Rule<Node\Stmt\Class_>
 */
final class ControllerHasFeatureTestRule implements Rule
{
    private const CONTROLLER_SUFFIX = 'Controller';

    private const CONTROLLERS_MARKER = '/Http/Controllers/';

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
        if (!\str_ends_with($className, self::CONTROLLER_SUFFIX)) {
            return [];
        }

        $fileName = \str_replace('\\', '/', $scope->getFile());

        // Только контроллеры внутри модулей
        if (!\str_contains($fileName, '/Modules/')) {
            return [];
        }

        $markerPos = \strpos($fileName, self::CONTROLLERS_MARKER);
        if ($markerPos === false) {
            return [];
        }

        // Корень модуля — всё до /Http/Controllers/
        $moduleRoot = \substr($fileName, 0, $markerPos);
        $featureDir = $moduleRoot . '/Tests/Feature';

        $shortName = \substr($className, 0, -\strlen(self::CONTROLLER_SUFFIX));

        $candidates = [
            $className . 'Test.php',
            $shortName . 'Test.php',
        ];

        foreach ($candidates as $candidate) {
            if (\is_file($featureDir . '/' . $candidate)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Controller "%s" must have a feature test. Expected "%s" in Tests/Feature/.',
                $className,
                \implode('" or "', $candidates),
            ))
                ->line($node->getLine())
                ->identifier('controller.featureTest.missing')
                ->build(),
        ];
    }
}
