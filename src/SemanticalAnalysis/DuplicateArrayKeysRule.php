<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects duplicate array keys in array literals.
 *
 * This rule identifies:
 * - Arrays with duplicate string keys where the values differ
 * - Arrays with duplicate string keys where the values are identical
 *
 * For duplicate keys with different values, it suggests removing the outdated one.
 * For duplicate keys with identical values, it suggests safely removing the duplicate pair.
 *
 * @implements Rule<Array_>
 */
class DuplicateArrayKeysRule implements Rule
{
    private const string MESSAGE_DUPLICATE_KEY = 'CUSTOM: The key is duplicated (and you should remove the outdated one).';
    private const string MESSAGE_DUPLICATE_PAIR = 'CUSTOM: The key-value pair is duplicated (and you can safely remove it).';

    public function getNodeType(): string
    {
        return Array_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Array_) {
            return [];
        }

        $processedKeys = [];
        $errors = [];

        foreach ($node->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            $key = $item->key;
            // Only check string literal keys (no variables, no expressions)
            if (!$key instanceof String_) {
                continue;
            }

            // Skip if it's an interpolated string (Encapsed)
            if ($key instanceof \PhpParser\Node\Scalar\Encapsed) {
                continue;
            }

            // Skip numeric strings (like '0', '1', etc.)
            if (is_numeric($key->value)) {
                continue;
            }

            // Skip if the key contains any variables or complex expressions
            if ($this->keyContainsVariables($key) || $this->isEvaluatedVariable($key)) {
                continue;
            }

            $keyValue = $key->value;
            $value = $item->value;

            if (isset($processedKeys[$keyValue])) {
                $existingValue = $processedKeys[$keyValue];

                $equivalent = $this->areValuesEquivalent($value, $existingValue);
                $debugInfo = ' (equiv: ' . ($equivalent ? 'true' : 'false') . ', types: ' . get_class($value) . '/' . get_class($existingValue);
                if ($value instanceof \PhpParser\Node\Scalar\String_) {
                    $debugInfo .= ', val1: ' . $value->value;
                }
                if ($existingValue instanceof \PhpParser\Node\Scalar\String_) {
                    $debugInfo .= ', val2: ' . $existingValue->value;
                }
                $debugInfo .= ')';

                if ($equivalent) {
                    // Key-value pair is duplicated
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_DUPLICATE_PAIR . $debugInfo)
                        ->identifier('array.duplicateKeyValuePair')
                        ->line($item->getStartLine())
                        ->build();
                } else {
                    // Key is duplicated but values differ
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_DUPLICATE_KEY . $debugInfo)
                        ->identifier('array.duplicateKey')
                        ->line($key->getStartLine())
                        ->build();
                }
            } else {
                $processedKeys[$keyValue] = $value;
            }
        }

        return $errors;
    }

    private function areValuesEquivalent(Node $value1, Node $value2): bool
    {
        // For simple string literals, compare the values directly
        if ($value1 instanceof \PhpParser\Node\Scalar\String_ &&
            $value2 instanceof \PhpParser\Node\Scalar\String_) {
            return $value1->value === $value2->value;
        }

        // For simple numeric literals, compare the values directly
        if ($value1 instanceof \PhpParser\Node\Scalar\LNumber &&
            $value2 instanceof \PhpParser\Node\Scalar\LNumber) {
            return $value1->value === $value2->value;
        }

        // For other scalar types
        if ($value1 instanceof \PhpParser\Node\Scalar &&
            $value2 instanceof \PhpParser\Node\Scalar) {
            if (property_exists($value1, 'value') && property_exists($value2, 'value')) {
                return $value1->value === $value2->value;
            }
        }

        // For more complex cases, use structural comparison
        return $this->nodesAreStructurallyEqual($value1, $value2);
    }

    private function nodesAreStructurallyEqual(Node $node1, Node $node2): bool
    {
        // Must be the same type of node
        if (get_class($node1) !== get_class($node2)) {
            return false;
        }

        // Compare all sub-nodes recursively
        foreach ($node1->getSubNodeNames() as $name) {
            $sub1 = $node1->$name;
            $sub2 = $node2->$name;

            if ($sub1 instanceof Node && $sub2 instanceof Node) {
                if (!$this->nodesAreStructurallyEqual($sub1, $sub2)) {
                    return false;
                }
            } elseif ($sub1 instanceof Node || $sub2 instanceof Node) {
                // One is a node, the other is not
                return false;
            } elseif (is_array($sub1) && is_array($sub2)) {
                if (count($sub1) !== count($sub2)) {
                    return false;
                }
                foreach ($sub1 as $key => $value1) {
                    if (!isset($sub2[$key])) {
                        return false;
                    }
                    $value2 = $sub2[$key];
                    if ($value1 instanceof Node && $value2 instanceof Node) {
                        if (!$this->nodesAreStructurallyEqual($value1, $value2)) {
                            return false;
                        }
                    } elseif ($value1 !== $value2) {
                        return false;
                    }
                }
            } elseif ($sub1 !== $sub2) {
                return false;
            }
        }

        return true;
    }

    private function keyContainsVariables(String_ $key): bool
    {
        // For now, skip keys that look like they might be variables
        // This is a heuristic since we can't easily traverse the original AST
        $value = $key->value;

        // Skip if it looks like a variable name
        if (preg_match('/^\$[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            return true;
        }

        // Skip if it contains variable-like patterns
        if (strpos($value, '$') !== false) {
            return true;
        }

        return false;
    }

    private function isEvaluatedVariable(String_ $key): bool
    {
        // Check if this String_ node has attributes that suggest it came from evaluating a variable
        // This is a heuristic based on PHPStan behavior

        $attributes = $key->getAttributes();

        // If the node has a 'originalNode' or similar attribute, it might be transformed
        if (isset($attributes['originalNode'])) {
            return true;
        }

        // Check for other indicators that this might be an evaluated expression
        // For now, we'll use a simple heuristic: if the string value matches a pattern
        // that suggests it came from a variable evaluation

        // This is not perfect, but it's better than nothing
        return false;
    }
}