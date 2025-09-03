<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\DateTime;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects misused strtotime() function calls and suggests optimizations.
 *
 * This rule identifies two common performance issues with strtotime():
 * 1. strtotime("now") should be replaced with time() (2x faster)
 * 2. strtotime(..., time()) can be simplified by removing the redundant time() call
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\FuncCall>
 */
final class StrtotimeUsageRule implements Rule
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

        if ((string) $functionName !== 'strtotime') {
            return [];
        }

        $argCount = count($node->args);
        if ($argCount === 0 || $argCount > 2) {
            return [];
        }

        // Case 1: strtotime("now") -> time()
        if ($argCount === 1) {
            $firstArg = $node->args[0]->value;
            if ($firstArg instanceof String_ && strcasecmp($firstArg->value, 'now') === 0) {
                return [
                    RuleErrorBuilder::message("'time()' should be used instead (2x faster).")
                        ->identifier('strtotime.useTime')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        // Case 2: strtotime(..., time()) -> strtotime(...)
        if ($argCount === 2) {
            $secondArg = $node->args[1]->value;
            if ($secondArg instanceof FuncCall) {
                $innerFunctionName = $secondArg->name;
                if ($innerFunctionName instanceof Node\Name &&
                    (string) $innerFunctionName === 'time' &&
                    count($secondArg->args) === 0) {
                    return [
                        RuleErrorBuilder::message("'time()' is default valued already, it can safely be removed.")
                            ->identifier('strtotime.unnecessaryTime')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }
}