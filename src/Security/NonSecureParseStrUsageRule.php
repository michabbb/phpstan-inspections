<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects insecure usage of parse_str() and mb_parse_str() functions.
 *
 * This rule identifies calls to parse_str() or mb_parse_str() that have only one argument,
 * which causes variables to be extracted into the global scope. This is a security vulnerability
 * known as "Variable Extract Vulnerability".
 *
 * The rule detects:
 * - parse_str($string) - insecure, extracts to global scope
 * - mb_parse_str($string) - insecure, extracts to global scope
 *
 * Recommended fix:
 * - parse_str($string, $result) - extracts to $result array instead
 * - mb_parse_str($string, $result) - extracts to $result array instead
 *
 * @implements Rule<FuncCall>
 */
class NonSecureParseStrUsageRule implements Rule
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

        // Check if this is a function call
        if (!$node->name instanceof Node\Name) {
            return [];
        }

        $functionName = $node->name->toString();

        // Check if it's parse_str or mb_parse_str
        if (!in_array($functionName, ['parse_str', 'mb_parse_str'], true)) {
            return [];
        }

        // Check if there is only one argument
        if (count($node->args) !== 1) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Please provide second parameter to not influence globals.')
                ->identifier('security.parseStr.insecure')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}