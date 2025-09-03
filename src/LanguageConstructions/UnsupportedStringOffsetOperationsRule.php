<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\ArrayDimFetch as ArrayAccessExpression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\StringType;

/**
 * Rule to detect unsupported string offset operations in PHP 7.1+
 *
 * This rule identifies operations that can provoke PHP Fatal errors when
 * attempting to use string variables as arrays:
 *
 * - Using string offset as an array: $string[$i][$j] (nested access)
 * - Using [] operator on strings: $string[] (push operation without index)
 *
 * These operations became fatal errors in PHP 7.1 due to changes in
 * string to array conversion behavior.
 *
 * @implements Rule<ArrayDimFetch>
 */
class UnsupportedStringOffsetOperationsRule implements Rule
{
    private const string MESSAGE_OFFSET = 'Could provoke a PHP Fatal error (cannot use string offset as an array).';
    private const string MESSAGE_PUSH = 'Could provoke a PHP Fatal error ([] operator not supported for strings).';

    public function getNodeType(): string
    {
        return ArrayDimFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ArrayDimFetch) {
            return [];
        }

        // Only check if we have a valid target expression
        $target = $node->var;
        if (!$this->isValidTarget($target)) {
            return [];
        }

        // Get the type of the target
        $targetType = $scope->getType($target);

        // Check if target is a string type
        if (!$this->isStringType($targetType)) {
            return [];
        }

        // Determine which case we're dealing with
        if ($this->isNestedArrayAccess($node)) {
            // Case 1: Nested array access (string offset used as array)
            return [
                RuleErrorBuilder::message(self::MESSAGE_OFFSET)
                    ->identifier('stringOffset.unsupportedOperation')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        if ($this->isPushOperation($node)) {
            // Case 2: Push operation without index
            return [
                RuleErrorBuilder::message(self::MESSAGE_PUSH)
                    ->identifier('stringOffset.unsupportedOperation')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Check if the target expression is a valid candidate for this rule
     */
    private function isValidTarget(Node\Expr $target): bool
    {
        return $target instanceof Variable
            || $target instanceof PropertyFetch
            || $target instanceof StaticPropertyFetch
            || $target instanceof ArrayDimFetch;
    }

    /**
     * Check if the given type is a string type
     */
    private function isStringType(\PHPStan\Type\Type $type): bool
    {
        // Check if it's a direct string type
        if ($type instanceof StringType) {
            return true;
        }

        // Check if it's a union type containing string
        if ($type instanceof \PHPStan\Type\UnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof StringType) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if this is a nested array access (case 1)
     */
    private function isNestedArrayAccess(ArrayDimFetch $node): bool
    {
        // This is nested access if the parent is also an ArrayDimFetch
        return $node->getAttribute('parent') instanceof ArrayDimFetch;
    }

    /**
     * Check if this is a push operation without index (case 2)
     */
    private function isPushOperation(ArrayDimFetch $node): bool
    {
        // This is a push operation if dim is null (no index provided)
        return $node->dim === null;
    }
}