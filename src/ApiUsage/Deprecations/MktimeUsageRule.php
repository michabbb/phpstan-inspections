<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Deprecations;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects deprecated usage of mktime() and gmmktime() functions.
 *
 * This rule identifies compatibility issues with mktime() and gmmktime() functions:
 * - Using these functions without arguments should be replaced with time()
 * - Using the 7th parameter (is_dst) is deprecated and removed in PHP 7
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\FuncCall>
 */
final class MktimeUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        $functionName = $node->name;
        if (!$functionName instanceof Node\Name) {
            return [];
        }

        $functionNameString = (string) $functionName;
        if ($functionNameString !== 'mktime' && $functionNameString !== 'gmmktime') {
            return [];
        }

        $arguments = $node->args;

        // Case 1: No arguments - suggest using time() instead
        if (count($arguments) === 0) {
            return [
                RuleErrorBuilder::message('You should use time() function instead (current usage produces a runtime warning).')
                    ->identifier('function.mktimeUsage')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        // Case 2: 7 arguments - warn about deprecated is_dst parameter
        if (count($arguments) === 7) {
            return [
                RuleErrorBuilder::message('Parameter \'is_dst\' is deprecated and removed in PHP 7.')
                    ->identifier('function.mktimeDeprecatedParameter')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}