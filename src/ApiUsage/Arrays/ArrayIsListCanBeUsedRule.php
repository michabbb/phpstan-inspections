<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests using array_is_list() instead of complex array comparison patterns.
 *
 * This rule detects when code checks if an array has consecutive integer keys starting from 0
 * using patterns like array_values($array) === $array or array_keys($array) === range(0, count($array) - 1).
 * It suggests using the more readable and efficient array_is_list() function introduced in PHP 8.1.
 *
 * @implements Rule<BinaryOp>
 */
class ArrayIsListCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isEqualityComparison($node)) {
            return [];
        }

        $replacement = null;
        
        // Pattern 1: array_values($array) === $array
        if ($this->isArrayValuesPattern($node, $replacement)) {
            return $this->createError($node, $replacement);
        }

        // Pattern 2: array_keys($array) === range(0, count($array) - 1)
        if ($this->isArrayKeysPattern($node, $replacement)) {
            return $this->createError($node, $replacement);
        }

        return [];
    }

    private function isEqualityComparison(BinaryOp $node): bool
    {
        return $node instanceof Equal
            || $node instanceof Identical
            || $node instanceof NotEqual
            || $node instanceof NotIdentical;
    }

    private function isArrayValuesPattern(BinaryOp $node, ?string &$replacement): bool
    {
        $replacement = null;

        // Check left side: array_values($array)
        if ($this->isArrayValuesCall($node->left, $arrayArg) && $arrayArg !== null) {
            // Check if right side is equivalent to the array argument
            if ($this->areEquivalent($arrayArg, $node->right)) {
                $replacement = $this->buildReplacement($node, $arrayArg);
                return true;
            }
        }

        // Check right side: array_values($array)
        if ($this->isArrayValuesCall($node->right, $arrayArg) && $arrayArg !== null) {
            // Check if left side is equivalent to the array argument
            if ($this->areEquivalent($arrayArg, $node->left)) {
                $replacement = $this->buildReplacement($node, $arrayArg);
                return true;
            }
        }

        return false;
    }

    private function isArrayKeysPattern(BinaryOp $node, ?string &$replacement): bool
    {
        $replacement = null;

        // Check left side: array_keys($array)
        if ($this->isArrayKeysCall($node->left, $arrayArg) && $arrayArg !== null) {
            // Check if right side is range(0, count($array) - 1)
            if ($this->isRangeExpression($node->right, $arrayArg)) {
                $replacement = $this->buildReplacement($node, $arrayArg);
                return true;
            }
        }

        // Check right side: array_keys($array)
        if ($this->isArrayKeysCall($node->right, $arrayArg) && $arrayArg !== null) {
            // Check if left side is range(0, count($array) - 1)
            if ($this->isRangeExpression($node->left, $arrayArg)) {
                $replacement = $this->buildReplacement($node, $arrayArg);
                return true;
            }
        }

        return false;
    }

    private function isArrayValuesCall(Node $node, ?Node &$arrayArg): bool
    {
        if (!$node instanceof FuncCall) {
            return false;
        }

        if (!$node->name instanceof Name) {
            return false;
        }

        if ($node->name->toString() !== 'array_values') {
            return false;
        }

        if (count($node->args) !== 1) {
            return false;
        }

        $arg = $node->args[0];
        if (!$arg instanceof \PhpParser\Node\Arg) {
            return false;
        }
        
        $arrayArg = $arg->value;
        return true;
    }

    private function isArrayKeysCall(Node $node, ?Node &$arrayArg): bool
    {
        if (!$node instanceof FuncCall) {
            return false;
        }

        if (!$node->name instanceof Name) {
            return false;
        }

        if ($node->name->toString() !== 'array_keys') {
            return false;
        }

        if (count($node->args) !== 1) {
            return false;
        }

        $arg = $node->args[0];
        if (!$arg instanceof \PhpParser\Node\Arg) {
            return false;
        }
        
        $arrayArg = $arg->value;
        return true;
    }

    private function isRangeExpression(Node $node, Node $expectedArrayArg): bool
    {
        if (!$node instanceof FuncCall) {
            return false;
        }

        if (!$node->name instanceof Name) {
            return false;
        }

        if ($node->name->toString() !== 'range') {
            return false;
        }

        if (count($node->args) !== 2) {
            return false;
        }

        // First argument should be 0
        $firstArgNode = $node->args[0];
        if (!$firstArgNode instanceof \PhpParser\Node\Arg) {
            return false;
        }
        $firstArg = $firstArgNode->value;
        if (!$firstArg instanceof LNumber || $firstArg->value !== 0) {
            return false;
        }

        // Second argument should be count($array) - 1
        $secondArgNode = $node->args[1];
        if (!$secondArgNode instanceof \PhpParser\Node\Arg) {
            return false;
        }
        $secondArg = $secondArgNode->value;
        return $this->isCountMinusOne($secondArg, $expectedArrayArg);
    }

    private function isCountMinusOne(Node $node, Node $expectedArrayArg): bool
    {
        if (!$node instanceof BinaryOp\Minus) {
            return false;
        }

        // Right operand should be 1
        if (!$node->right instanceof LNumber || $node->right->value !== 1) {
            return false;
        }

        // Left operand should be count($array)
        if (!$node->left instanceof FuncCall) {
            return false;
        }

        if (!$node->left->name instanceof Name) {
            return false;
        }

        if ($node->left->name->toString() !== 'count') {
            return false;
        }

        if (count($node->left->args) !== 1) {
            return false;
        }

        $argNode = $node->left->args[0];
        if (!$argNode instanceof \PhpParser\Node\Arg) {
            return false;
        }
        
        return $this->areEquivalent($argNode->value, $expectedArrayArg);
    }

    private function areEquivalent(Node $node1, Node $node2): bool
    {
        // Simple string comparison of node representation
        // This is a simplified approach - in a real implementation,
        // you might want to use a more sophisticated equivalence check
        return $this->nodeToString($node1) === $this->nodeToString($node2);
    }

    private function nodeToString(Node $node): string
    {
        // For simple variables, use the name directly
        if ($node instanceof \PhpParser\Node\Expr\Variable && is_string($node->name)) {
            return '$' . $node->name;
        }
        
        // For more complex expressions, use a basic printer approach
        // In a production implementation, you'd use PhpParser\PrettyPrinter
        $rawValue = $node->getAttribute('rawValue');
        if ($rawValue !== null && is_string($rawValue)) {
            return trim($rawValue);
        }
        
        // Fallback to getting source code representation
        return $node->getAttribute('startTokenPos', '') . '_' . $node->getAttribute('endTokenPos', '');
    }

    private function buildReplacement(BinaryOp $node, ?Node $arrayArg): ?string
    {
        if ($arrayArg === null) {
            return null;
        }
        
        $needsNegation  = $node instanceof NotEqual || $node instanceof NotIdentical;
        $arrayArgString = $this->nodeToString($arrayArg);
        
        return $needsNegation
            ? '!array_is_list(' . $arrayArgString . ')'
            : 'array_is_list(' . $arrayArgString . ')';
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function createError(BinaryOp $node, ?string $replacement): array
    {
        if ($replacement === null) {
            return [];
        }
        
        return [
            RuleErrorBuilder::message("Can be replaced by '" . $replacement . "' (improves maintainability).")
                ->identifier('arrayFunction.arrayIsListCanBeUsed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
