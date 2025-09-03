<?php

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unused parameters in closure functions.
 *
 * This rule identifies closure parameters that are defined but never used within
 * the closure body. Unused parameters can indicate dead code or potential bugs.
 * Parameters starting with underscore (_) are ignored as they are commonly used
 * for intentionally unused parameters.
 *
 * @implements Rule<Node\Expr\Closure>
 */
class UnusedClosureParameterRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\Closure::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\Expr\Closure) {
            return [];
        }

        $params = $node->params;
        if (empty($params)) {
            return [];
        }

        $usedVariableNames = $this->findUsedVariables($node->stmts);

        $errors = [];
        foreach ($params as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $paramName = $param->var->name;

                // Ignore variables starting with _
                if (str_starts_with($paramName, '_')) {
                    continue;
                }

                if (!isset($usedVariableNames[$paramName])) {
                    $errors[] = RuleErrorBuilder::message(
                        "Closure parameter \$$paramName is unused."
                    )->line($param->getStartLine())->build();
                }
            }
        }

        return $errors;
    }

    /**
     * @param Node\Stmt[] $stmts
     * @return array<string, true>
     */
    private function findUsedVariables(array $stmts): array
    {
        $traverser = new NodeTraverser();
        $visitor = new class extends NodeVisitorAbstract {
            /** @var array<string, true> */
            public array $usedVariableNames = [];

            public function enterNode(Node $node) {
                if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
                    $this->usedVariableNames[$node->name] = true;
                }
                return null;
            }
        };
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->usedVariableNames;
    }
}
