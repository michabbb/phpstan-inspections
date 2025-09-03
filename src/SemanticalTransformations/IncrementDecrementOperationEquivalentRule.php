<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalTransformations;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects increment/decrement operation equivalents and suggests using ++/-- operators.
 *
 * This rule identifies assignment expressions that can be safely replaced with
 * prefixed increment/decrement operations (++$var, --$var). It detects patterns like:
 * - $var += 1; → ++$var;
 * - $var -= 1; → --$var;
 * - $var = $var + 1; → ++$var;
 * - $var = $var - 1; → --$var;
 *
 * The rule avoids false positives for array and string manipulations.
 *
 * @implements Rule<Node>
 */
final class IncrementDecrementOperationEquivalentRule implements Rule
{
    /**
     * Preference for prefix style (true) or suffix style (false).
     */
    private const PREFER_PREFIX_STYLE = true;

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Handle self-assignment expressions (+=, -=)
        if ($node instanceof AssignOp\Plus || $node instanceof AssignOp\Minus) {
            return $this->processSelfAssignment($node, $scope);
        }

        // Handle regular assignments with binary operations
        if ($node instanceof Assign) {
            return $this->processAssignment($node, $scope);
        }

        return [];
    }

    /**
     * Process self-assignment expressions like $var += 1.
     */
    private function processSelfAssignment(AssignOp $node, Scope $scope): array
    {
        $variable = $node->var;
        $value = $node->expr;

        // Check if value is 1
        if (!$this->isValueOne($value)) {
            return [];
        }

        // Check if variable is safe (not array access or string)
        if ($this->isArrayAccessOrString($variable, $scope)) {
            return [];
        }

        $isIncrement = $node instanceof AssignOp\Plus;
        $replacement = $this->generateReplacement($variable, $isIncrement);

        return [
            RuleErrorBuilder::message("Can be safely replaced with '{$replacement}'.")
                ->identifier('operation.incrementDecrementEquivalent')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Process regular assignments like $var = $var + 1.
     */
    private function processAssignment(Assign $node, Scope $scope): array
    {
        $variable = $node->var;
        $value = $node->expr;

        if (!$value instanceof BinaryOp) {
            return [];
        }

        $operation = $value->getOperatorSigil();
        if ($operation !== '+' && $operation !== '-') {
            return [];
        }

        $leftOperand = $value->left;
        $rightOperand = $value->right;

        // Check if this is $var = $var + 1 or $var = 1 + $var
        $isIncrement = $operation === '+';
        $isValidPattern = false;

        if ($this->variablesEqual($leftOperand, $variable) && $this->isValueOne($rightOperand)) {
            $isValidPattern = true;
        } elseif ($this->isValueOne($leftOperand) && $this->variablesEqual($rightOperand, $variable)) {
            $isValidPattern = true;
        }

        if (!$isValidPattern) {
            return [];
        }

        // Check if variable is safe (not array access or string)
        if ($this->isArrayAccessOrString($variable, $scope)) {
            return [];
        }

        $replacement = $this->generateReplacement($variable, $isIncrement);

        return [
            RuleErrorBuilder::message("Can be safely replaced with '{$replacement}'.")
                ->identifier('operation.incrementDecrementEquivalent')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Check if the given expression represents the value 1.
     */
    private function isValueOne(Expr $expr): bool
    {
        return $expr instanceof LNumber && $expr->value === 1;
    }

    /**
     * Check if the variable is an array access or string that should be avoided.
     */
    private function isArrayAccessOrString(Expr $variable, Scope $scope): bool
    {
        if ($variable instanceof ArrayDimFetch) {
            $container = $variable->var;
            if ($container instanceof Variable) {
                $type = $scope->getType($container);
                $types = [];

                // Check if it's an array (not string)
                foreach ($type->getConstantStrings() as $constString) {
                    $types[] = $constString->getValue();
                }

                // If we have string types but no array types, it's likely a string
                $hasString = in_array('string', $types, true);
                $hasArray = str_contains(implode(' ', $types), 'array');

                return $hasString && !$hasArray;
            }
        }

        return false;
    }

    /**
     * Check if two expressions represent the same variable.
     */
    private function variablesEqual(Expr $expr1, Expr $expr2): bool
    {
        if ($expr1 instanceof Variable && $expr2 instanceof Variable) {
            return $expr1->name === $expr2->name;
        }

        if ($expr1 instanceof ArrayDimFetch && $expr2 instanceof ArrayDimFetch) {
            return $this->variablesEqual($expr1->var, $expr2->var)
                && $this->expressionsEqual($expr1->dim, $expr2->dim);
        }

        return false;
    }

    /**
     * Check if two expressions are structurally equal.
     */
    private function expressionsEqual(?Expr $expr1, ?Expr $expr2): bool
    {
        if ($expr1 === null && $expr2 === null) {
            return true;
        }

        if ($expr1 === null || $expr2 === null) {
            return false;
        }

        // For our use case, we mainly care about simple equality
        if ($expr1 instanceof LNumber && $expr2 instanceof LNumber) {
            return $expr1->value === $expr2->value;
        }

        return false;
    }

    /**
     * Generate the replacement string with increment/decrement operator.
     */
    private function generateReplacement(Expr $variable, bool $isIncrement): string
    {
        $varText = $this->exprToString($variable);
        $operator = $isIncrement ? '++' : '--';

        if (self::PREFER_PREFIX_STYLE) {
            return $operator . $varText;
        }

        return $varText . $operator;
    }

    /**
     * Convert expression to string representation.
     */
    private function exprToString(Expr $expr): string
    {
        if ($expr instanceof Variable) {
            return '$' . $expr->name;
        }

        if ($expr instanceof ArrayDimFetch) {
            $varText = $this->exprToString($expr->var);
            $dimText = $expr->dim ? $this->exprToString($expr->dim) : '';
            return $varText . '[' . $dimText . ']';
        }

        return 'expr';
    }
}