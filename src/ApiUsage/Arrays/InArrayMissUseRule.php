<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\UnaryOp\BooleanNot;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\TypeUtils;

/**
 * Detects misused in_array() function calls that can be optimized or simplified.
 *
 * This rule identifies several patterns of in_array() misuse:
 * - in_array($needle, array_keys($array)) → array_key_exists($needle, $array)
 * - in_array($needle, [$singleValue]) → $needle == $singleValue
 * - in_array($needle, []) → false (always false)
 *
 * Suggests more efficient or clearer alternatives for better performance and readability.
 *
 * @implements Rule<FuncCall>
 */
final class InArrayMissUseRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name || (string) $node->name !== 'in_array') {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 2 || count($args) > 3) {
            return [];
        }

        $needle = $args[0]->value;
        $haystack = $args[1]->value;
        $strict = false;
        if (isset($args[2])) {
            $strictType = $scope->getType($args[2]->value);
            if ($strictType instanceof ConstantBooleanType && $strictType->getValue() === true) {
                $strict = true;
            }
        }

        // Pattern 1: in_array($needle, array_keys($haystack))
        if ($haystack instanceof FuncCall && $haystack->name instanceof Name && (string) $haystack->name === 'array_keys') {
            $arrayKeysArgs = $haystack->getArgs();
            if (count($arrayKeysArgs) === 1) {
                $originalArray = $arrayKeysArgs[0]->value;
                $replacement = sprintf('array_key_exists(%s, %s)', $this->printNode($needle), $this->printNode($originalArray));
                return [
                    RuleErrorBuilder::message(
                        sprintf("'%s' should be used instead. It is safe to refactor for type-safe code when the indexes are integers/strings only.", $replacement)
                    )
                    ->identifier('arrayKeyExists.inArrayMissUse')
                    ->line($node->getStartLine())
                    ->build(),
                ];
            }
        }

        // Pattern 2: in_array($needle, [$singleValue]) or in_array($needle, [])
        if ($haystack instanceof Array_) {
            $items = $haystack->items;
            $itemsCount = count($items);

            if ($itemsCount <= 1) {
                $checkExists = true;
                $targetNode = $node;

                // Determine if the in_array call is negated or part of a comparison
                $parentNode = $node->getAttribute('parent');
                if ($parentNode instanceof BooleanNot && $parentNode->expr === $node) {
                    $checkExists = false;
                    $targetNode = $parentNode;
                } elseif ($parentNode instanceof BinaryOp) {
                    $isBooleanComparison = false;
                    if ($parentNode->right instanceof ConstFetch && ((string) $parentNode->right->name === 'true' || (string) $parentNode->right->name === 'false')) {
                        $isBooleanComparison = true;
                    } elseif ($parentNode->left instanceof ConstFetch && ((string) $parentNode->left->name === 'true' || (string) $parentNode->left->name === 'false')) {
                        $isBooleanComparison = true;
                    }

                    if ($isBooleanComparison) {
                        $isTrueComparison = ($parentNode->right instanceof ConstFetch && (string) $parentNode->right->name === 'true') || ($parentNode->left instanceof ConstFetch && (string) $parentNode->left->name === 'true');
                        $isFalseComparison = ($parentNode->right instanceof ConstFetch && (string) $parentNode->right->name === 'false') || ($parentNode->left instanceof ConstFetch && (string) $parentNode->left->name === 'false');

                        if ($parentNode instanceof Equal || $parentNode instanceof Identical) {
                            $checkExists = $isTrueComparison;
                        } elseif ($parentNode instanceof NotEqual || $parentNode instanceof NotIdentical) {
                            $checkExists = $isFalseComparison;
                        }
                        $targetNode = $parentNode;
                    }
                }

                if ($itemsCount === 0) {
                    // in_array($needle, []) is always false
                    $replacement = $checkExists ? 'false' : 'true';
                    return [
                        RuleErrorBuilder::message(
                            sprintf("'%s' should be used instead.", $replacement)
                        )
                        ->identifier('comparison.inArrayMissUse')
                        ->line($targetNode->getStartLine())
                        ->build(),
                    ];
                }

                // itemsCount === 1
                /** @var ArrayItem $singleItem */
                $singleItem = $items[0];
                $singleValue = $singleItem->value;

                $comparisonOperator = $checkExists
                    ? ($strict ? '===' : '==')
                    : ($strict ? '!==' : '!=');

                $replacement = sprintf('%s %s %s', $this->printNode($needle), $comparisonOperator, $this->printNode($singleValue));
                return [
                    RuleErrorBuilder::message(
                        sprintf("'%s' should be used instead.", $replacement)
                    )
                    ->identifier('comparison.inArrayMissUse')
                    ->line($targetNode->getStartLine())
                    ->build(),
                ];
            }
        }

        return [];
    }

    private function printNode(Node $node): string
    {
        $printer = new \PhpParser\PrettyPrinter\Standard();
        return $printer->prettyPrintExpr($node);
    }
}
