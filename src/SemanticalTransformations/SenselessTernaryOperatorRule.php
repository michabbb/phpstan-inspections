<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalTransformations;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Ternary;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects senseless ternary operators that can be simplified.
 *
 * This rule identifies ternary expressions where:
 * - The condition is a binary expression using === or !== operators
 * - The true and false branches return the same values as the operands in the condition
 * - The expression can be simplified to just one of the operands
 *
 * Examples:
 * - $a === $b ? $a : $b; → can be simplified to $b
 * - $a !== $b ? $b : $a; → can be simplified to $a
 * - $x === $y ? $x : $y; → can be simplified to $y
 * - $x !== $y ? $y : $x; → can be simplified to $x
 *
 * @implements Rule<Ternary>
 */
final class SenselessTernaryOperatorRule implements Rule
{
    public function getNodeType(): string
    {
        return Ternary::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Ternary) {
            return [];
        }

        // Check if condition is a binary expression with === or !==
        $condition = $this->unwrapParentheses($node->cond);
        if (!$condition instanceof BinaryOp\Identical && !$condition instanceof BinaryOp\NotIdentical) {
            return [];
        }

        $isInverted = $condition instanceof BinaryOp\NotIdentical;

        // Get the operands
        $leftOperand = $condition->left;
        $rightOperand = $condition->right;

        // Get the true and false variants
        $trueVariant = $this->unwrapParentheses($node->if);
        $falseVariant = $this->unwrapParentheses($node->else);

        if ($trueVariant === null || $falseVariant === null) {
            return [];
        }

        // Check if operands match the ternary branches
        $leftMatchesTrue = $this->expressionsAreEqual($leftOperand, $trueVariant);
        $leftMatchesFalse = $this->expressionsAreEqual($leftOperand, $falseVariant);
        $rightMatchesTrue = $this->expressionsAreEqual($rightOperand, $trueVariant);
        $rightMatchesFalse = $this->expressionsAreEqual($rightOperand, $falseVariant);

        // For ===: we want left === right ? left : right → right
        // For !==: we want left !== right ? right : left → left
        if ($isInverted) {
            // !== case: left !== right ? right : left
            if ($leftMatchesFalse && $rightMatchesTrue) {
                $replacement = $this->getExpressionText($leftOperand);
                if ($replacement !== null) {
                    return [
                        RuleErrorBuilder::message(
                            "Can be replaced with '{$replacement}' (reduces cyclomatic complexity and cognitive load)."
                        )
                            ->identifier('ternary.senselessOperator')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }
        } else {
            // === case: left === right ? left : right
            if ($leftMatchesTrue && $rightMatchesFalse) {
                $replacement = $this->getExpressionText($rightOperand);
                if ($replacement !== null) {
                    return [
                        RuleErrorBuilder::message(
                            "Can be replaced with '{$replacement}' (reduces cyclomatic complexity and cognitive load)."
                        )
                            ->identifier('ternary.senselessOperator')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }

    /**
     * Unwrap parentheses from an expression.
     */
    private function unwrapParentheses(Expr $expr): Expr
    {
        while ($expr instanceof Expr\Paren) {
            $expr = $expr->expr;
        }
        return $expr;
    }

    /**
     * Check if two expressions are structurally equal.
     */
    private function expressionsAreEqual(Expr $expr1, Expr $expr2): bool
    {
        // Simple structural equality check
        // For more complex cases, we could use PHPStan's expression comparison utilities
        return $this->getExpressionText($expr1) === $this->getExpressionText($expr2);
    }

    /**
     * Get a simple text representation of an expression for comparison and replacement.
     */
    private function getExpressionText(Expr $expr): ?string
    {
        // Handle variables
        if ($expr instanceof Expr\Variable) {
            return '$' . $expr->name;
        }

        // Handle constants
        if ($expr instanceof Expr\ConstFetch && $expr->name instanceof Expr\Name) {
            return $expr->name->toString();
        }

        // Handle property access
        if ($expr instanceof Expr\PropertyFetch) {
            $varText = $this->getExpressionText($expr->var);
            if ($varText === null) {
                return null;
            }

            $propName = $expr->name instanceof Expr\Identifier
                ? $expr->name->toString()
                : ($expr->name instanceof Expr ? $this->getExpressionText($expr->name) : null);

            if ($propName === null) {
                return null;
            }

            return $varText . '->' . $propName;
        }

        // Handle array access
        if ($expr instanceof Expr\ArrayDimFetch) {
            $varText = $this->getExpressionText($expr->var);
            if ($varText === null) {
                return null;
            }

            $dimText = $expr->dim !== null ? $this->getExpressionText($expr->dim) : '';
            return $varText . '[' . $dimText . ']';
        }

        // For other expressions, return null to indicate we can't represent them simply
        return null;
    }
}