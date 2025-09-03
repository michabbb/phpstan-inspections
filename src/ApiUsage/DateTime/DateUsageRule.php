<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\DateTime;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unnecessary arguments in date() function calls.
 *
 * This rule identifies when date() is called with time() as the second argument,
 * which is redundant since time() is the default value for the timestamp parameter.
 * Suggests removing the unnecessary time() call for cleaner code.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\FuncCall>
 */
final class DateUsageRule implements Rule
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

        if ((string) $functionName !== 'date') {
            return [];
        }

        if (count($node->args) !== 2) {
            return [];
        }

        $secondArgument = $node->args[1]->value;
        if (!$secondArgument instanceof FuncCall) {
            return [];
        }

        $innerFunctionName = $secondArgument->name;
        if (!$innerFunctionName instanceof Node\Name) {
            return [];
        }

        if ((string) $innerFunctionName === 'time' && count($secondArgument->args) === 0) {
            return [
                RuleErrorBuilder::message("'time()' is default valued already, it can safely be removed.")
                    ->identifier('date.unnecessaryTime')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
