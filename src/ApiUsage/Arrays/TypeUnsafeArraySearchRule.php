<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

/**
 * Rule to detect usage of array_search() and in_array() without strict mode
 * 
 * Detects cases where the third parameter (strict) is missing from array_search()
 * and in_array() function calls, which can lead to type-unsafe comparisons.
 * 
 * @implements Rule<FuncCall>
 */
class TypeUnsafeArraySearchRule implements Rule
{
    private const array TARGET_FUNCTIONS = ['array_search', 'in_array'];
    
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();
        if (!in_array($functionName, self::TARGET_FUNCTIONS, true)) {
            return [];
        }

        // Only check calls with exactly 2 parameters (missing the third strict parameter)
        if (count($node->getArgs()) !== 2) {
            return [];
        }

        $args = $node->getArgs();
        $needleArg = $args[0];
        $haystackArg = $args[1];

        // False positive prevention: array of string literals
        if ($haystackArg->value instanceof Array_ && $this->isStringLiteralArray($haystackArg->value)) {
            return [];
        }

        // False positive prevention: complementary types
        if ($this->areTypesComplementary($needleArg->value, $haystackArg->value, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Third parameter should be provided to clarify if type safety is important in this context.')
                ->identifier('arraySearch.missingStrict')
                ->tip('Add true as the third parameter for strict type comparison: ' . $functionName . '($needle, $haystack, true)')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Check if array consists only of non-numeric string literals
     */
    private function isStringLiteralArray(Array_ $array): bool
    {
        if (count($array->items) === 0) {
            return false;
        }

        $validStringCount = 0;
        foreach ($array->items as $item) {
            if ($item === null || !$item instanceof ArrayItem) {
                continue;
            }
            
            if (!$item->value instanceof String_) {
                return false;
            }

            $content = trim($item->value->value);
            if ($content === '' || (preg_match('/^\d+$/', $content) === 1)) {
                return false;
            }
            
            $validStringCount++;
        }

        // Must have at least one valid non-numeric string and all elements must be valid
        return $validStringCount > 0 && $validStringCount === count($array->items);
    }

    /**
     * Check if needle and haystack element types are complementary
     */
    private function areTypesComplementary(Expr $needle, Expr $haystack, Scope $scope): bool
    {
        $needleType = $scope->getType($needle);
        $haystackType = $scope->getType($haystack);

        // For array types, get the element type
        if ($haystackType->isArray()->yes()) {
            $arrayTypes = $haystackType->getArrays();
            if (count($arrayTypes) > 0) {
                $haystackElementType = $arrayTypes[0]->getItemType();
                
                // Check if needle type matches array element type exactly
                if ($needleType->isSuperTypeOf($haystackElementType)->yes() && 
                    $haystackElementType->isSuperTypeOf($needleType)->yes()) {
                    return true;
                }
            }
        }

        return false;
    }
}