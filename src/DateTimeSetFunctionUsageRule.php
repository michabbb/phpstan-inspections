<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects incorrect usage of date_time_set() function with microseconds parameter.
 *
 * This rule identifies calls to date_time_set() with 5 arguments (including microseconds)
 * and reports that the call will return false if the PHP version is below 7.1.
 *
 * The rule detects:
 * - date_time_set($object, $hour, $minute, $second, $microseconds) - should be avoided in PHP < 7.1
 *
 * @implements Rule<FuncCall>
 */
class DateTimeSetFunctionUsageRule implements Rule
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

        // Check if function name is date_time_set
        if (!$node->name instanceof Node\Name || $node->name->toString() !== 'date_time_set') {
            return [];
        }

        // Check if exactly 5 arguments (object, hour, minute, second, microseconds)
        if (count($node->getArgs()) !== 5) {
            return [];
        }

        // Report on the microseconds parameter (5th argument)
        $microsecondsArg = $node->getArgs()[4];

        return [
            RuleErrorBuilder::message("The call will return false ('microseconds' parameter is available in PHP 7.1+).")
                ->identifier('datetime.setTimeUsage')
                ->line($microsecondsArg->getStartLine())
                ->build(),
        ];
    }
}