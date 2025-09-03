<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Ifs;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\If_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects inverted 'if-else' constructs and suggests avoiding unnecessary negations.
 *
 * This rule identifies if-else statements where the condition is unnecessarily inverted
 * using the NOT operator (!) or by comparing with false (=== false). Such constructs
 * can be simplified by removing the inversion and swapping the if/else branches.
 *
 * Examples of violations:
 * - if (!$condition) { // false case } else { // true case }
 * - if ($condition === false) { // false case } else { // true case }
 *
 * Recommended fixes:
 * - if ($condition) { // true case } else { // false case }
 *
 * @implements Rule<Else_>
 */
class InvertedIfElseConstructsRule implements Rule
{
    public function getNodeType(): string
    {
        return If_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof If_) {
            return [];
        }

        // Check if this If statement has an else clause
        if ($node->else === null) {
            return []; // No else clause, not an if-else construct
        }

        $condition = $node->cond;

        // DEBUG: Show condition type
        if ($condition === null) {
            return [
                RuleErrorBuilder::message(
                    'DEBUG: Condition is null'
                )
                ->identifier('ifElse.debug')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        // Check for inverted conditions
        if ($this->isInvertedCondition($condition)) {
            return [
                RuleErrorBuilder::message(
                    'The if-else workflow is driven by inverted conditions, consider avoiding inversions.'
                )
                ->identifier('ifElse.inverted')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        return [];
    }

    /**
     * Check if a condition is inverted (using NOT operator or === false)
     */
    private function isInvertedCondition(Node $condition): bool
    {
        // Check for unary NOT: !$condition
        if ($condition instanceof BooleanNot) {
            return true;
        }

        // Check for === false or false ===
        if ($condition instanceof Identical) {
            $left = $condition->left;
            $right = $condition->right;

            // Check if left is false and right is some expression
            if ($this->isFalseLiteral($left) && !$this->isFalseLiteral($right)) {
                return true;
            }

            // Check if right is false and left is some expression
            if ($this->isFalseLiteral($right) && !$this->isFalseLiteral($left)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a node represents the literal false value
     */
    private function isFalseLiteral(Node $node): bool
    {
        return $node instanceof Node\Expr\ConstFetch
            && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'false';
    }
}