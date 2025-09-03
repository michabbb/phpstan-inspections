<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects ternary operators that can be simplified when both branches return boolean constants.
 *
 * This rule identifies ternary expressions where:
 * - The condition is a binary expression
 * - Both the true and false branches are boolean constants (true/false)
 * - The expression can be simplified to just the condition or its negation
 *
 * Examples:
 * - $variable = $number > 0 ? true : false; → $variable = $number > 0;
 * - $variable = $number > 0 ? false : true; → $variable = !($number > 0);
 * - $variable = $number & $flag ? true : false; → $variable = (bool)($number & $flag);
 *
 * @implements Rule<Ternary>
 */
final class TernaryOperatorSimplifyRule implements Rule
{
    /**
     * Map of operators to their opposites for negation.
     * @var array<string, string>
     */
    private const OPPOSITE_OPERATORS = [
        '==' => '!=',
        '===' => '!==',
        '!=' => '==',
        '!==' => '===',
        '>' => '<=',
        '<' => '>=',
        '>=' => '<',
        '<=' => '>',
    ];

    public function getNodeType(): string
    {
        return Ternary::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Ternary) {
            return [];
        }

        // Check if both branches are boolean constants
        $trueVariant = $this->unwrapParentheses($node->if);
        $falseVariant = $this->unwrapParentheses($node->else);

        if (!$this->isBooleanConstant($trueVariant) || !$this->isBooleanConstant($falseVariant)) {
            return [];
        }

        // Check if condition is a binary expression
        $condition = $this->unwrapParentheses($node->cond);
        if (!$condition instanceof BinaryOp) {
            return [];
        }

        // Generate replacement suggestion
        $replacement = $this->generateReplacement($condition, $this->isFalseConstant($trueVariant));

        if ($replacement === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "'{$replacement}' should be used instead (reduces cyclomatic and cognitive complexity)."
            )
                ->identifier('languageConstructions.ternaryOperatorSimplify')
                ->line($node->getStartLine())
                ->build(),
        ];
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
     * Check if an expression is a boolean constant (true or false).
     */
    private function isBooleanConstant(Expr $expr): bool
    {
        if (!$expr instanceof ConstFetch) {
            return false;
        }

        if (!$expr->name instanceof Name) {
            return false;
        }

        $name = strtolower($expr->name->toString());
        return $name === 'true' || $name === 'false';
    }

    /**
     * Check if an expression is the false constant.
     */
    private function isFalseConstant(Expr $expr): bool
    {
        if (!$expr instanceof ConstFetch) {
            return false;
        }

        if (!$expr->name instanceof Name) {
            return false;
        }

        return strtolower($expr->name->toString()) === 'false';
    }

    /**
     * Generate the replacement string for the ternary expression.
     */
    private function generateReplacement(BinaryOp $condition, bool $isInverted): ?string
    {
        $operator = $this->getOperatorText($condition);

        if ($operator === null) {
            return null;
        }

        $conditionText = $this->getConditionText($condition);

        // Check if we need parentheses (for logical operators)
        $useParentheses = $this->isLogicalOperator($operator);

        if ($useParentheses) {
            $boolCasting = $isInverted ? '!' : '(bool)';
            return $boolCasting . '(' . $conditionText . ')';
        }

        if ($isInverted) {
            $oppositeOperator = self::OPPOSITE_OPERATORS[$operator] ?? null;
            if ($oppositeOperator === null) {
                return null;
            }

            $leftText = $this->getExpressionText($condition->left);
            $rightText = $this->getExpressionText($condition->right);

            if ($leftText === null || $rightText === null) {
                return null;
            }

            return $leftText . ' ' . $oppositeOperator . ' ' . $rightText;
        }

        return $conditionText;
    }

    /**
     * Get the operator text from a binary operation.
     */
    private function getOperatorText(BinaryOp $op): ?string
    {
        return match (get_class($op)) {
            Expr\BinaryOp\Equal::class => '==',
            Expr\BinaryOp\Identical::class => '===',
            Expr\BinaryOp\NotEqual::class => '!=',
            Expr\BinaryOp\NotIdentical::class => '!==',
            Expr\BinaryOp\Greater::class => '>',
            Expr\BinaryOp\GreaterOrEqual::class => '>=',
            Expr\BinaryOp\Less::class => '<',
            Expr\BinaryOp\LessOrEqual::class => '<=',
            Expr\BinaryOp\BooleanAnd::class => '&&',
            Expr\BinaryOp\BooleanOr::class => '||',
            default => null,
        };
    }

    /**
     * Check if an operator is a logical operator (needs parentheses).
     */
    private function isLogicalOperator(string $operator): bool
    {
        return $operator === '&&' || $operator === '||';
    }

    /**
     * Get the text representation of a condition.
     */
    private function getConditionText(BinaryOp $condition): string
    {
        $leftText = $this->getExpressionText($condition->left);
        $rightText = $this->getExpressionText($condition->right);
        $operator = $this->getOperatorText($condition);

        if ($leftText === null || $rightText === null || $operator === null) {
            return '...';
        }

        return $leftText . ' ' . $operator . ' ' . $rightText;
    }

    /**
     * Get the text representation of an expression.
     */
    private function getExpressionText(Expr $expr): ?string
    {
        // Handle variables
        if ($expr instanceof Expr\Variable) {
            return '$' . $expr->name;
        }

        // Handle constants
        if ($expr instanceof ConstFetch && $expr->name instanceof Name) {
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

        // Handle method calls
        if ($expr instanceof Expr\MethodCall) {
            $varText = $this->getExpressionText($expr->var);
            if ($varText === null) {
                return null;
            }

            $methodName = $expr->name instanceof Expr\Identifier
                ? $expr->name->toString()
                : ($expr->name instanceof Expr ? $this->getExpressionText($expr->name) : null);

            if ($methodName === null) {
                return null;
            }

            return $varText . '->' . $methodName . '()';
        }

        // Handle function calls
        if ($expr instanceof Expr\FuncCall && $expr->name instanceof Name) {
            $functionName = $expr->name->toString();
            $args = [];
            foreach ($expr->args as $arg) {
                $argText = $this->getExpressionText($arg->value);
                if ($argText === null) {
                    return null;
                }
                $args[] = $argText;
            }
            return $functionName . '(' . implode(', ', $args) . ')';
        }

        // For other expressions, return null to indicate we can't represent them
        return null;
    }
}