<?php

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NullType;

/**
 * Detects redundant null coalescing operators (??).
 *
 * This rule identifies unnecessary uses of the null coalescing operator when:
 * - The left operand cannot be null
 * - The left operand is already guaranteed to be a non-null array
 * - The right operand is an empty array and the left cannot be null
 *
 * Suggests removing redundant ?? operators for cleaner code.
 *
 * @implements Rule<Node\Expr\BinaryOp\Coalesce>
 */
class RedundantNullCoalescingRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\BinaryOp\Coalesce::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\Expr\BinaryOp\Coalesce) {
            return [];
        }

        $left = $node->left;
        $right = $node->right;

        // Check if the right operand is an empty array
        if (!($right instanceof Node\Expr\Array_) || !empty($right->items)) {
            return [];
        }

        // Get the type of the left operand
        $leftType = $scope->getType($left);
        
        // Check if the left operand cannot be null
        if (!$leftType->isSuperTypeOf(new NullType())->yes()) {
            return [
                RuleErrorBuilder::message(
                    'Redundant null-coalescing operator: The left operand cannot be null, so ?? [] is unnecessary.'
                )->build()
            ];
        }

        // Check if the variable is guaranteed to be an array and not null
        if ($leftType->isArray()->yes() && !$leftType->accepts(new NullType(), true)->yes()) {
            return [
                RuleErrorBuilder::message(
                    'Redundant null-coalescing operator: The variable is already guaranteed to be a non-null array.'
                )->build()
            ];
        }

        return [];
    }

}