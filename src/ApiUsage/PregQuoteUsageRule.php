<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures proper usage of preg_quote() function with delimiter parameter.
 *
 * This rule detects when preg_quote() is called with only one argument, missing
 * the required delimiter parameter. Without the delimiter, preg_quote() may not
 * properly escape special regex characters that match the delimiter, leading to
 * potential security issues or incorrect regex behavior.
 *
 * @implements Rule<FuncCall>
 */
class PregQuoteUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower($node->name->toString());
        if ($functionName !== 'preg_quote') {
            return [];
        }

        // Check if only one argument is provided (missing delimiter)
        if (count($node->getArgs()) === 1) {
            return [
                RuleErrorBuilder::message('Please provide regex delimiter as the second argument for proper escaping.')
                    ->identifier('function.pregQuoteDelimiter')
                    ->tip('Use preg_quote($string, $delimiter) to ensure proper escaping of special characters')
                    ->build(),
            ];
        }

        return [];
    }
}
