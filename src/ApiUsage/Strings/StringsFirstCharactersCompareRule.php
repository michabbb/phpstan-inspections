<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates strncmp() and strncasecmp() function calls for correct length parameters.
 *
 * This rule checks when strncmp() or strncasecmp() are called with a length parameter
 * that doesn't match the length of the provided string literal. This can indicate
 * a potential bug where the comparison length is incorrect.
 *
 * @implements Rule<FuncCall>
 */
final class StringsFirstCharactersCompareRule implements Rule
{
    private const string MESSAGE = 'The specified length doesn\'t match the string length.';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        // Only for global functions strncmp / strncasecmp with exactly 3 arguments
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower($node->name->toString());
        if ($functionName !== 'strncmp' && $functionName !== 'strncasecmp') {
            return [];
        }

        if (count($node->args) !== 3) {
            return [];
        }

        // Third arg must be numeric literal (int) to match the original inspector intent
        $lengthArg = $node->args[2] ?? null;
        if (!$lengthArg instanceof Arg || !$lengthArg->value instanceof LNumber) {
            return [];
        }

        $lengthLiteral = $lengthArg->value;
        $providedLength = (int) $lengthLiteral->value;

        // Either first or second argument must be a string literal
        $stringArg = $this->getLiteralStringArg($node->args[0] ?? null, $node->args[1] ?? null);
        if ($stringArg === null) {
            return [];
        }

        $literal = $stringArg->value;
        if (!$literal instanceof String_) {
            return [];
        }

        // PhpParser already unescapes string content in ->value
        $literalValue = $literal->value;
        $stringLength = strlen($literalValue);

        // Mirror EA behavior: only report when length differs and literal length > 0
        if ($stringLength === 0) {
            return [];
        }

        if ($stringLength === $providedLength) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('strings.firstCharactersCompare.lengthMismatch')
                ->line($lengthLiteral->getStartLine())
                ->build(),
        ];
    }

    private function getLiteralStringArg(?Arg $first, ?Arg $second): ?Arg
    {
        if ($first instanceof Arg && $first->value instanceof String_) {
            return $first;
        }
        if ($second instanceof Arg && $second->value instanceof String_) {
            return $second;
        }
        return null;
    }
}

