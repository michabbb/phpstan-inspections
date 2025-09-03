<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects case-insensitive string functions that can be replaced with case-sensitive alternatives.
 *
 * When searching for patterns that don't contain alphabetic characters, case-insensitive functions
 * like stristr(), stripos(), strripos() can be replaced with their case-sensitive counterparts
 * strstr(), strpos(), strrpos() for better performance.
 *
 * @implements \PHPStan\Rules\Rule<FuncCall>
 */
final class CaseInsensitiveStringFunctionsMissUseRule implements Rule
{
    private const MAPPING = [
        'stristr' => 'strstr',
        'stripos' => 'strpos',
        'strripos' => 'strrpos',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        $functionName = $node->name instanceof Node\Name ? (string) $node->name : null;
        if ($functionName === null || !isset(self::MAPPING[$functionName])) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 2 || count($args) > 3) {
            return [];
        }

        $patternArg = $args[1]->value;
        if (!$patternArg instanceof String_) {
            // We only care about string literals for the pattern, as per the Java inspector
            return [];
        }

        $patternString = $patternArg->value;

        // Check if the pattern is not empty and does not contain any alphabetic characters
        // \p{L} matches any kind of letter from any language
        if ($patternString !== '' && !preg_match('/\\p{L}/u', $patternString)) {
            $replacementFunctionName = self::MAPPING[$functionName];
            $message = sprintf(
                '\'%s(...)\' should be used instead (the pattern does not contain alphabet characters).',
                $replacementFunctionName
            );

            return [
                RuleErrorBuilder::message($message)
                    ->identifier('string.caseInsensitiveFunctionMissUse')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
