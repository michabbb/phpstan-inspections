<?php

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects nested ternary operators that reduce code readability.
 *
 * This rule identifies ternary expressions that contain other ternary expressions
 * either in their condition or in their true branch. Nested ternary operators
 * can be very hard to read and understand, leading to maintainability issues.
 * Suggests using if-else statements or extracting to separate functions instead.
 *
 * @implements Rule<Node\Expr\Ternary>
 */
class NoNestedTernaryRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\Ternary::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\Expr\Ternary) {
            return [];
        }

        $condExpr = $this->unwrapParen($node->cond);
        $ifExpr = $node->if ? $this->unwrapParen($node->if) : null;

        if ($condExpr instanceof Node\Expr\Ternary || $ifExpr instanceof Node\Expr\Ternary) {
            return [
                RuleErrorBuilder::message(
                    'Nested ternary operator should not be used (maintainability issues).'
                )->build()
            ];
        }

        return [];
    }

    private function unwrapParen(Node\Expr $expr): Node\Expr
    {
        while ($expr instanceof Node\Expr\Paren) {
            $expr = $expr->expr;
        }
        return $expr;
    }
}
