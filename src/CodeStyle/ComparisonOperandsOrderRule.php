<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\BinaryOp\SmallerOrEqual;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects inconsistent comparison operands order and enforces Yoda or regular style.
 *
 * This rule identifies comparison operations where one operand is a constant (literal or named constant)
 * and the other is a variable/expression. It enforces consistent style:
 * - Yoda style: constant on the left (e.g., 0 === $x)
 * - Regular style: constant on the right (e.g., $x === 0)
 *
 * The rule helps maintain code consistency and can prevent accidental assignments
 * in conditions when using the == operator instead of ===.
 *
 * @implements Rule<Node>
 */
final class ComparisonOperandsOrderRule implements Rule
{
    private const COMPARISON_OPERATORS = [
        Equal::class,
        NotEqual::class,
        Identical::class,
        NotIdentical::class,
        Greater::class,
        GreaterOrEqual::class,
        Smaller::class,
        SmallerOrEqual::class,
    ];

    public function __construct(
        private bool $useYodaStyle = true
    ) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof BinaryOp) {
            return [];
        }

        if (!$this->isComparisonOperator($node)) {
            return [];
        }

        $leftIsConstant = $this->isConstant($node->left, $scope);
        $rightIsConstant = $this->isConstant($node->right, $scope);

        // Both are constants or both are not constants - no issue
        if ($leftIsConstant === $rightIsConstant) {
            return [];
        }

        if ($this->useYodaStyle) {
            // Yoda style: constant should be on the left
            if ($rightIsConstant && !$leftIsConstant) {
                return [
                    RuleErrorBuilder::message('Yoda conditions style should be used instead.')
                        ->identifier('comparisonOperandsOrder.yodaStyle')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        } else {
            // Regular style: constant should be on the right
            if ($leftIsConstant && !$rightIsConstant) {
                return [
                    RuleErrorBuilder::message('Regular conditions style should be used instead.')
                        ->identifier('comparisonOperandsOrder.regularStyle')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function isComparisonOperator(BinaryOp $node): bool
    {
        return in_array($node::class, self::COMPARISON_OPERATORS, true);
    }

    private function isConstant(Node $node, Scope $scope): bool
    {
        // Check for scalar literals (strings, numbers, etc.)
        if ($node instanceof Scalar) {
            return true;
        }

        // Check for constant references (true, false, null, defined constants)
        if ($node instanceof ConstFetch) {
            return true;
        }

        // Check for constant expressions that evaluate to constants
        $type = $scope->getType($node);
        return $type->isConstantValue()->yes();
    }
}