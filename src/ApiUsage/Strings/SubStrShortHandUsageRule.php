<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects redundant substr() and mb_substr() calls that can be simplified.
 *
 * This rule identifies unnecessary substr() calls like:
 * - substr($var, 0) → $var (redundant substring from start)
 * - substr($var, 0, strlen($var)) → $var (substring of entire string)
 * - mb_substr($var, 0) → $var
 * - mb_substr($var, 0, mb_strlen($var)) → $var
 *
 * @implements Rule<FuncCall>
 */
final class SubStrShortHandUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof FuncCall) {
            return [];
        }

        $functionName = $this->resolveFunctionName($node);
        if (! in_array($functionName, ['substr', 'mb_substr'], true)) {
            return [];
        }

        if (count($node->getArgs()) < 2) {
            return []; // substr needs at least 2 arguments
        }

        $stringVar = $node->getArgs()[0]->value;
        $offset = $node->getArgs()[1]->value;

        // Check if offset is 0
        if (! ($offset instanceof LNumber && $offset->value === 0)) {
            return [];
        }

        // Case 1: substr($var, 0, strlen($var)) or mb_substr($var, 0, mb_strlen($var))
        if (isset($node->getArgs()[2])) {
            $length = $node->getArgs()[2]->value;
            if ($length instanceof FuncCall) {
                $lengthFunctionName = $this->resolveFunctionName($length);
                if (in_array($lengthFunctionName, ['strlen', 'mb_strlen'], true)) {
                    if (count($length->getArgs()) === 1) {
                        $lengthVar = $length->getArgs()[0]->value;
                        if ($this->areVariablesEquivalent($stringVar, $lengthVar)) {
                            return [
                                RuleErrorBuilder::message(sprintf('Redundant %s() usage. Can be simplified to variable.', $functionName))
                                    ->identifier('string.redundantSubstr')
                                    ->line($node->getStartLine())
                                    ->build(),
                            ];
                        }
                    }
                }
            }
        } else {
            // Case 2: substr($var, 0) or mb_substr($var, 0)
            return [
                RuleErrorBuilder::message(sprintf('Redundant %s() usage. Can be simplified to variable.', $functionName))
                    ->identifier('string.redundantSubstr')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function resolveFunctionName(FuncCall $funcCall): ?string
    {
        if ($funcCall->name instanceof Node\Name) {
            return (string) $funcCall->name;
        }

        return null;
    }

    private function areVariablesEquivalent(Node\Expr $var1, Node\Expr $var2): bool
    {
        if ($var1 instanceof Variable && $var2 instanceof Variable) {
            return $var1->name === $var2->name;
        }
        // More complex equivalence checks could be added here if needed,
        // e.g., for properties or array access, but for now, simple variable name comparison is sufficient.
        return false;
    }
}
