<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Флагирует вызовы потенциальных DB-методов внутри циклов (Foreach/For).
 *
 * @implements Rule<Node>
 */
final class DbQueryInLoopRule implements Rule
{
    /**
     * @var array<string, true>
     */
    private array $suspiciousMethods = [
        'first'          => true,
        'find'           => true,
        'get'            => true,
        'pluck'          => true,
        'exists'         => true,
        'count'          => true,
        'sum'            => true,
        'avg'            => true,
        'min'            => true,
        'max'            => true,
        'insert'         => true,
        'upsert'         => true,
        'update'         => true,
        'delete'         => true,
        'create'         => true,
        'save'           => true,
        'paginate'       => true,
        'simplepaginate' => true,
        'cursorpaginate' => true,
        'where'          => true,
        'wherein'        => true,
        'wherenotin'     => true,
        'wherenull'      => true,
        'wherenotnull'   => true,
        'orderby'        => true,
        'groupby'        => true,
        'having'         => true,
        'join'           => true,
        'leftjoin'       => true,
        'rightjoin'      => true,
    ];

    /**
     * @var array<string, true>
     */
    private array $builderClasses = [
        'Illuminate\\Database\\Eloquent\\Builder'             => true,
        'Illuminate\\Database\\Query\\Builder'                => true,
        'Illuminate\\Database\\Eloquent\\Relations\\Relation' => true,
        'Illuminate\\Database\\Eloquent\\Model'               => true,
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

        $stmts = $node->stmts;

        foreach ($this->flatten($stmts) as $stmt) {
            if ($stmt instanceof MethodCall) {
                $methodName = $stmt->name instanceof Node\Identifier ? \strtolower($stmt->name->toString()) : null;
                if ($methodName === null) {
                    continue;
                }

                if (!isset($this->suspiciousMethods[$methodName])) {
                    continue;
                }

                $callerType = $scope->getType($stmt->var);
                foreach ($callerType->getObjectClassNames() as $className) {
                    if (isset($this->builderClasses[$className])) {
                        $errors[] = RuleErrorBuilder::message(
                            \sprintf(
                                'DB query call "%s" inside loop may cause N+1 or high complexity. Move it outside the loop or batch the query.',
                                $methodName,
                            ),
                        )
                            ->line($stmt->getLine())
                            ->identifier('performance.dbQueryInLoop')
                            ->build();
                        break;
                    }
                }
            }

            if ($stmt instanceof StaticCall && $stmt->class instanceof Node\Name) {
                $className  = $stmt->class->toString();
                $methodName = $stmt->name instanceof Node\Identifier ? \strtolower($stmt->name->toString()) : null;

                if ($methodName !== null && isset($this->suspiciousMethods[$methodName])) {
                    $errors[] = RuleErrorBuilder::message(
                        \sprintf(
                            'Possible DB query "%s::%s()" inside loop. Consider batching or moving it outside the loop.',
                            $className,
                            $methodName,
                        ),
                    )
                        ->line($stmt->getLine())
                        ->identifier('performance.dbQueryInLoopStatic')
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
