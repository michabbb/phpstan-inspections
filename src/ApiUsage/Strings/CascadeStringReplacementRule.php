<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\RuleError;

/**
 * Detects cascading str_replace(...) calls that can be merged.
 *
 * This rule identifies:
 * - Cascading replacements: Multiple str_replace calls on the same variable that can be merged
 * - Nested replacements: str_replace calls with another str_replace as the subject
 * - Search simplification: Arrays with identical search values that can be simplified
 *
 * @implements Rule<FuncCall>
 */
final class CascadeStringReplacementRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @param FuncCall $node */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!($node->name instanceof Node\Name) || $node->name->toString() !== 'str_replace') {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) !== 3) {
            return [];
        }

        $errors = [];

        $errors = [];

        // Check for cascading replacements
        $cascadingError = $this->checkCascadingReplacements($node, $scope);
        if ($cascadingError !== null) {
            $errors[] = $cascadingError;
        }

        // Check for nested replacements
        $nestedError = $this->checkNestedReplacements($args[2]->value);
        if ($nestedError !== null) {
            $errors[] = $nestedError;
        }

        // Check for search simplification
        $simplificationError = $this->checkSearchSimplification($args[0]->value);
        if ($simplificationError !== null) {
            $errors[] = $simplificationError;
        }

        return $errors;
    }

    private function checkCascadingReplacements(FuncCall $node, Scope $scope): ?RuleError
    {
        // TODO: Implement cascading detection
        // This requires more complex AST traversal to find previous str_replace calls
        return null;
    }

    private function checkNestedReplacements(Node $subject): ?RuleError
    {
        if (!($subject instanceof FuncCall)) {
            return null;
        }

        if (!($subject->name instanceof Node\Name) || $subject->name->toString() !== 'str_replace') {
            return null;
        }

        // Check if we can merge the arguments
        $parentArgs = $subject->getArgs();
        if (count($parentArgs) !== 3) {
            return null;
        }

        return RuleErrorBuilder::message('This str_replace(...) call can be merged with its parent.')
            ->identifier('strReplace.nesting')
            ->line($subject->getStartLine())
            ->build();
    }

    private function checkSearchSimplification(Node $search): ?RuleError
    {
        if (!($search instanceof Node\Expr\Array_)) {
            return null;
        }

        $elements = $search->items;
        if (count($elements) < 2) {
            return null;
        }

        $firstValue = null;
        foreach ($elements as $element) {
            if ($element === null || $element->value === null) {
                return null;
            }

            if (!($element->value instanceof Node\Scalar\String_)) {
                return null;
            }

            if ($firstValue === null) {
                $firstValue = $element->value->value;
            } elseif ($element->value->value !== $firstValue) {
                return null;
            }
        }

        return RuleErrorBuilder::message('Can be replaced with the string from the array.')
            ->identifier('strReplace.searchSimplification')
            ->line($search->getStartLine())
            ->build();
    }


}