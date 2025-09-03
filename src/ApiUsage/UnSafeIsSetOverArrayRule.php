<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NullType;

/**
 * Analyzes isset() function usage for potential improvements.
 *
 * This rule checks isset() calls and suggests alternatives:
 * - Using 'null !== $var' for better type safety
 * - Using 'array_key_exists()' for array key checking
 * - Moving concatenation in array indexes to separate variables for better readability
 *
 * @implements Rule<Isset_>
 */
class UnSafeIsSetOverArrayRule implements Rule
{
    public function getNodeType(): string
    {
        return Isset_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Isset_) {
            return [];
        }

        // Only check isset calls with exactly one argument
        if (count($node->vars) !== 1) {
            return [];
        }

        $argument = $node->vars[0];
        
        // Debug: Force an error to verify this rule is being called
        if ($argument instanceof ArrayDimFetch) {
            // Check for concatenation in array indexes
            if ($this->hasConcatenationAsIndex($argument)) {
                return [
                    RuleErrorBuilder::message('Concatenation is used in an index, it should be moved to a variable.')
                        ->identifier('isset.concatenationInIndex')
                        ->tip('Extract concatenated index to a separate variable for better readability and debugging')
                        ->line($argument->getStartLine())
                        ->build()
                ];
            }
        }

        return [];
    }

    private function checkArrayAccess(ArrayDimFetch $argument, Isset_ $issetCall, Scope $scope): array
    {
        $errors = [];
        
        // Check for concatenation in array indexes - this is the main focus
        if ($this->hasConcatenationAsIndex($argument)) {
            $errors[] = RuleErrorBuilder::message('Concatenation is used in an index, it should be moved to a variable.')
                ->identifier('isset.concatenationInIndex')
                ->tip('Extract concatenated index to a separate variable for better readability and debugging')
                ->line($argument->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function hasConcatenationAsIndex(ArrayDimFetch $expression): bool
    {
        // Check the immediate index for concatenation
        if ($expression->dim instanceof Concat) {
            return true;
        }
        
        // Check nested array access expressions
        $current = $expression->var;
        while ($current instanceof ArrayDimFetch) {
            if ($current->dim instanceof Concat) {
                return true;
            }
            $current = $current->var;
        }
        
        return false;
    }

    private function shouldSuggestArrayKeyExists(ArrayDimFetch $expression, Scope $scope): bool
    {
        $containerType = $scope->getType($expression->var);
        
        // Only suggest for definite array types to reduce noise
        if ($containerType->isArray()->yes()) {
            // Don't suggest if we already know the key doesn't exist or always exists
            // Let PHPStan's built-in rules handle those cases
            return true;
        }
        
        // Check for constant arrays
        $constantArrays = $containerType->getConstantArrays();
        if (count($constantArrays) > 0) {
            return true;
        }
        
        return false;
    }
}