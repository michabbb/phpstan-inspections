<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates printf/scanf function calls for correct format strings and argument counts.
 *
 * This rule checks printf-style functions (printf, sprintf, fprintf) and scanf-style
 * functions (sscanf, fscanf) to ensure that:
 * - Format strings are valid and properly formed
 * - The number of provided arguments matches the expected parameters in the format string
 * - Position specifiers in format strings are handled correctly
 *
 * @implements Rule<FuncCall>
 */
class PrintfScanfArgumentsRule implements Rule
{
    private const string MESSAGE_PATTERN    = 'Format string pattern seems to be not valid.';
    private const string MESSAGE_PARAMETERS = 'Number of expected parameters is %d.';

    private const array FUNCTIONS = [
        'printf'  => 0,
        'sprintf' => 0,
        'sscanf'  => 1,
        'fprintf' => 1,
        'fscanf'  => 1,
    ];

    private const string PLACEHOLDER_REGEX = '/%((\\d+|\\*)\\$)?[+-]?(?:[ 0]|\\\\?\'.)?-?\\d*(?:\\.\\d*)?[sducoxXbgGeEfF]/';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();
        if (!isset(self::FUNCTIONS[$functionName])) {
            return [];
        }

        $patternPosition             = self::FUNCTIONS[$functionName];
        $minimumArgumentsForAnalysis = $patternPosition + 1;
        
        $args = $node->getArgs();
        if (count($args) < $minimumArgumentsForAnalysis) {
            return [];
        }

        $patternArg = $args[$patternPosition];
        if (!$patternArg->value instanceof String_) {
            return [];
        }

        $pattern = $patternArg->value->value;
        $content = trim($pattern);
        
        if (empty($content)) {
            return [];
        }

        // Normalize content: remove %% and check for inline variables
        $contentAdapted = str_replace('%%', '', $content);
        $contentNoVars  = preg_replace('/\$\{?\$?[a-zA-Z0-9]+\}?/', '', $contentAdapted);
        
        // Skip if variables were found in the pattern
        if ($contentAdapted !== $contentNoVars) {
            return [];
        }

        // Find valid placeholders and extract position specifiers
        $countWithoutPositionSpecifier = 0;
        $maxPositionSpecifier          = 0;
        $countParsedAll                = 0;

        preg_match_all(self::PLACEHOLDER_REGEX, $contentAdapted, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $countParsedAll++;
            
            if (isset($match[2]) && is_numeric($match[2])) {
                $maxPositionSpecifier = max($maxPositionSpecifier, (int) $match[2]);
                continue;
            }
            
            $countWithoutPositionSpecifier++;
        }

        $expectedParametersCount = $minimumArgumentsForAnalysis + max($countWithoutPositionSpecifier, $maxPositionSpecifier);

        // Check for pattern validity
        $parametersInPattern = substr_count(str_replace(['%%', '%*'], ['', ''], $content), '%');
        if ($countParsedAll !== $parametersInPattern) {
            return [
                RuleErrorBuilder::message(self::MESSAGE_PATTERN)
                    ->identifier('function.printfScanf.invalidPattern')
                    ->line($patternArg->value->getStartLine())
                    ->build(),
            ];
        }

        // Check for arguments matching
        if ($expectedParametersCount !== count($args)) {
            // Handle fscanf/sscanf special case - they can return an array if no containers provided
            if (count($args) === 2 && ($functionName === 'fscanf' || $functionName === 'sscanf')) {
                // Skip if function call is used in assignment or return context (returns array)
                $parent = $node->getAttribute('parent');
                if ($parent instanceof Assign ||
                    $parent instanceof Return_ ||
                    $parent instanceof Arg) {
                    return [];
                }
            }

            // Check for variadic arguments
            $lastArg = end($args);
            if ($lastArg && $lastArg->unpack) {
                return [];
            }

            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE_PARAMETERS, $expectedParametersCount))
                    ->identifier('function.printfScanf.argumentCount')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
