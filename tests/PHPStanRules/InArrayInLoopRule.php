<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Предупреждает об использовании in_array/array_search внутри циклов (Foreach/For),
 * что часто даёт квадратичную сложность. Рекомендуется заменить на hash-set (array_flip/array_fill_keys + isset()).
 *
 * @implements Rule<Node>
 */
final class InArrayInLoopRule implements Rule
{
    /**
     * @var array<string, true>
     */
    private array $badFunctions = [
        'in_array'     => true,
        'array_search' => true,
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!($node instanceof Foreach_ || $node instanceof For_)) {
            return [];
        }

        $errors = [];
        $stmts  = $node->stmts;

        foreach ($this->flatten($stmts) as $n) {
            if ($n instanceof FuncCall) {
                $name = $n->name instanceof Node\Name ? \strtolower($n->name->toString()) : null;
                if ($name !== null && isset($this->badFunctions[$name])) {
                    $errors[] = RuleErrorBuilder::message(
                        \sprintf(
                            'Function %s() inside loop may cause O(n^2). Use hash-set (array_flip + isset) or precompute outside the loop.',
                            $name,
                        ),
                    )
                        ->line($n->getLine())
                        ->identifier('performance.inArrayInLoop')
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<Node> $nodes
     *
     * @return iterable<Node>
     */
    private function flatten(array $nodes): iterable
    {
        foreach ($nodes as $n) {
            yield $n;
            foreach ($n->getSubNodeNames() as $name) {
                $sub = $n->$name ?? null;
                if ($sub instanceof Node) {
                    yield from $this->flatten([$sub]);
                } elseif (\is_array($sub)) {
                    $childNodes = [];
                    foreach ($sub as $maybeNode) {
                        if ($maybeNode instanceof Node) {
                            $childNodes[] = $maybeNode;
                        }
                    }
                    if ($childNodes !== []) {
                        yield from $this->flatten($childNodes);
                    }
                }
            }
        }
    }
}
