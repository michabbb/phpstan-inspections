<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects opportunities for packed hashtable optimizations based on array key order and types.
 * Mirrors Php Inspections (EA Extended) PackedHashtableOptimizationInspector.
 *
 * @implements Rule<Array_>
 */
final class PackedHashtableOptimizationRule implements Rule
{
    public const string MESSAGE_REORDER = 'Reordering keys in natural ascending order would enable array optimizations here.';
    public const string MESSAGE_USE_NUMERIC = 'Using integer keys would enable array optimizations here.';

    public function getNodeType(): string
    {
        return Array_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Array_) {
            return [];
        }

        // Require at least 3 items to be meaningful (mirrors EA requirement)
        if (count($node->items) < 3) {
            return [];
        }

        // Optional false-positives filter: skip typical test contexts
        $file = $scope->getFile();
        if (str_contains($file, '/tests/') || str_ends_with($file, 'Test.php')) {
            return [];
        }

        $indexes = [];
        foreach ($node->items as $item) {
            if ($item === null || $item->key === null) {
                // stop if any element lacks an explicit key
                $indexes = [];
                break;
            }

            $key = $item->key;
            if ($key instanceof String_ || $key instanceof LNumber || $key instanceof UnaryMinus || $key instanceof UnaryPlus) {
                $indexes[] = $key;
                continue;
            }

            // Not a supported literal numeric key
            $indexes = [];
            break;
        }

        if (count($indexes) !== count($node->items)) {
            return [];
        }

        $hasStringIndexes = false;
        $hasIncreasing    = true; // non-decreasing order
        $lastIndex        = PHP_INT_MIN;

        foreach ($indexes as $keyNode) {
            $numericIndex = null;
            $isString     = false;

            if ($keyNode instanceof String_) {
                $hasStringIndexes = true;
                $isString         = true;
                $contents         = $keyNode->value;

                // leading zero like '01' is not considered convertible
                if (strlen($contents) > 1 && $contents[0] === '0') {
                    return [];
                }

                // must be an integer numeric string
                if ($contents === '' || !preg_match('/^-?\d+$/', $contents)) {
                    return [];
                }

                $numericIndex = (int) $contents;
            } elseif ($keyNode instanceof LNumber) {
                $numericIndex = $keyNode->value;
            } elseif ($keyNode instanceof UnaryMinus || $keyNode instanceof UnaryPlus) {
                $inner = $keyNode->expr;
                if (!$inner instanceof LNumber) {
                    return [];
                }
                $numericIndex = $inner->value;
                if ($keyNode instanceof UnaryMinus) {
                    $numericIndex = -$numericIndex;
                }
            } else {
                // unsupported node
                return [];
            }

            if ($numericIndex < $lastIndex) {
                $hasIncreasing = false;
            }
            $lastIndex = $numericIndex;
        }

        $errors = [];
        if (!$hasIncreasing) {
            $errors[] = RuleErrorBuilder::message(self::MESSAGE_REORDER)
                ->identifier('array.packedHashtable.reorder')
                ->line($node->getStartLine())
                ->build();
        }
        if ($hasIncreasing && $hasStringIndexes) {
            $errors[] = RuleErrorBuilder::message(self::MESSAGE_USE_NUMERIC)
                ->identifier('array.packedHashtable.numericKeys')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }
}

