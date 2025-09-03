<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests using compact() instead of array creation with matching key-value pairs.
 *
 * This rule detects array creation expressions where all elements are key-value pairs
 * where the key is a string literal and the value is a variable with the same name as the key.
 * Such patterns can be replaced with the more concise compact() function.
 *
 * Example:
 * Instead of: ['foo' => $foo, 'bar' => $bar]
 * Use: compact('foo', 'bar')
 *
 * @implements Rule<Array_>
 */
class CompactCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return Array_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isTargetContext($node, $scope)) {
            return [];
        }

        $variables = $this->extractCompactVariables($node);

        if (count($variables) > 1) {
            $replacement = "compact('" . implode("', '", $variables) . "')";
            return [
                RuleErrorBuilder::message("'$replacement' can be used instead (improves maintainability).")
                    ->identifier('arrayFunction.compactCanBeUsed')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function isTargetContext(Array_ $node, Scope $scope): bool
    {
        $parent = $node->getAttribute('parent');

        // If no parent, it's a target context
        if ($parent === null) {
            return true;
        }

        // If parent is an assignment expression, check if our array is the value
        if ($parent instanceof \PhpParser\Node\Expr\Assign) {
            return $parent->expr === $node;
        }

        // For other contexts, allow them
        return true;
    }

    /**
     * @return list<string>
     */
    private function extractCompactVariables(Array_ $node): array
    {
        $variables = [];

        foreach ($node->items as $item) {
            if (!$item instanceof ArrayItem) {
                return [];
            }

            // Must have a key (not null)
            if ($item->key === null) {
                return [];
            }

            // Key must be a string literal
            if (!$item->key instanceof String_) {
                return [];
            }

            // Value must be a variable
            if (!$item->value instanceof Variable) {
                return [];
            }

            // Variable must be a string name
            if (!is_string($item->value->name)) {
                return [];
            }

            // Variable name must match the key
            $keyName = $item->key->value;
            $varName = $item->value->name;

            if ($keyName !== $varName) {
                return [];
            }

            $variables[] = $keyName;
        }

        return $variables;
    }
}