<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Ternary;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use PhpParser\PrettyPrinter\Standard;

/**
 * Detects when pow() function can be replaced with the ** operator.
 *
 * This rule identifies cases where the pow() function is used and suggests
 * replacing it with the ** operator, which is more readable and semantically
 * clearer for exponentiation since PHP 5.6.
 *
 * @implements Rule<FuncCall>
 */
final class PowerOperatorCanBeUsedRule implements Rule
{
    private Standard $prettyPrinter;

    public function __construct()
    {
        $this->prettyPrinter = new Standard();
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        // Check if it's a pow() function call
        $functionName = $this->getFunctionName($node, $scope);
        if ($functionName !== 'pow') {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) !== 2) {
            return [];
        }

        $baseArg = $args[0]->value;
        $powerArg = $args[1]->value;

        // Determine if parentheses are needed
        $wrapBase = $this->needsParentheses($baseArg);
        $wrapPower = $this->needsParentheses($powerArg);

        // Build replacement string
        $replacement = $this->buildReplacement($node, $baseArg, $powerArg, $wrapBase, $wrapPower);

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' can be used instead", $replacement)
            )
                ->identifier('function.powerOperatorCanBeUsed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function getFunctionName(FuncCall $funcCall, Scope $scope): ?string
    {
        if (!$funcCall->name instanceof Node\Name) {
            return null;
        }

        $resolvedName = $scope->resolveName($funcCall->name);
        if ($resolvedName === '') {
            return null;
        }

        return strtolower($resolvedName);
    }

    private function needsParentheses(Node $node): bool
    {
        return $node instanceof BinaryOp || $node instanceof Ternary;
    }

    private function buildReplacement(FuncCall $funcCall, Node $base, Node $power, bool $wrapBase, bool $wrapPower): string
    {
        $baseStr = $this->prettyPrinter->prettyPrintExpr($base);
        $powerStr = $this->prettyPrinter->prettyPrintExpr($power);

        // Apply parentheses if needed
        if ($wrapBase) {
            $baseStr = '(' . $baseStr . ')';
        }
        if ($wrapPower) {
            $powerStr = '(' . $powerStr . ')';
        }

        $replacement = $baseStr . ' ** ' . $powerStr;

        // If the pow() call is part of a binary operation, wrap the whole thing in parentheses
        if ($funcCall->getAttribute('parent') instanceof BinaryOp) {
            $replacement = '(' . $replacement . ')';
        }

        return $replacement;
    }
}