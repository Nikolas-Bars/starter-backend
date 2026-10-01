<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
final class NoDebugFunctionsRule implements Rule
{
    /**
     * @var array<string>
     */
    private array $forbiddenFunctions = [
        'dd',
        'dump',
        'var_dump',
        'print_r',
        'var_export',
        'debug_backtrace',
        'debug_print_backtrace',
        'xdebug_var_dump',
        'xdebug_debug_zval',
        'ray',
    ];

    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name) {
            return [];
        }

        $functionName = $node->name->toLowerString();

        if (\in_array($functionName, $this->forbiddenFunctions, true)) {
            return [
                RuleErrorBuilder::message(
                    \sprintf('Debug function "%s()" is not allowed in production code', $functionName),
                )
                    ->line($node->getLine())
                    ->identifier('debug.function.forbidden')
                    ->build(),
            ];
        }

        return [];
    }
}
