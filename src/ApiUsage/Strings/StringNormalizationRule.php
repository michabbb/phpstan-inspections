<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects suboptimal string normalization patterns and suggests improvements.
 *
 * This rule identifies:
 * - Inverted nesting: When length manipulation functions (trim, ltrim, rtrim, substr, mb_substr)
 *   are called on case manipulation functions, suggesting to swap the order
 * - Senseless nesting: When the same case manipulation function is called twice,
 *   or when certain combinations don't make sense
 *
 * @implements Rule<FuncCall>
 */
class StringNormalizationRule implements Rule
{
    private const array LENGTH_MANIPULATION_FUNCTIONS = [
        'trim',
        'ltrim',
        'rtrim',
        'substr',
        'mb_substr',
    ];

    private const array CASE_MANIPULATION_FUNCTIONS = [
        'strtolower',
        'strtoupper',
        'mb_convert_case',
        'mb_strtolower',
        'mb_strtoupper',
        'ucfirst',
        'lcfirst',
        'ucwords',
    ];

    private const array INNER_CASE_MANIPULATION_FUNCTIONS = [
        'strtolower',
        'strtoupper',
        'mb_convert_case',
        'mb_strtolower',
        'mb_strtoupper',
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

        $functionName = $this->getFunctionName($node);
        if ($functionName === null) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) === 0) {
            return [];
        }

        $firstArg = $args[0]->value;
        if (!$firstArg instanceof FuncCall) {
            return [];
        }

        $innerFunctionName = $this->getFunctionName($firstArg);
        if ($innerFunctionName === null) {
            return [];
        }

        $innerArgs = $firstArg->getArgs();
        if (count($innerArgs) === 0) {
            return [];
        }

        // Check for inverted nesting: length manipulation on case manipulation
        if ($this->isLengthManipulationFunction($functionName) &&
            $this->isCaseManipulationFunction($innerFunctionName)) {

            $isTarget = !$functionName === 'trim' ||
                       count($args) === 1 ||
                       $this->isNonLetterTrimArgument($args[1] ?? null);

            if ($isTarget) {
                $innerArgText = $this->getNodeText($innerArgs[0]->value);
                $newInnerCall = str_replace($this->getNodeText($firstArg), $innerArgText, $this->getNodeText($node));
                $replacement = str_replace($innerArgText, $newInnerCall, $this->getNodeText($firstArg));

                return [
                    RuleErrorBuilder::message(
                        sprintf("'%s' makes more sense here.", $replacement)
                    )
                    ->identifier('string.normalization.inverted')
                    ->line($node->getStartLine())
                    ->build(),
                ];
            }
        }

        // Check for senseless nesting: same case manipulation function twice
        if ($this->isCaseManipulationFunction($functionName) &&
            $this->isCaseManipulationFunction($innerFunctionName)) {

            if ($functionName === $innerFunctionName) {
                return [
                    RuleErrorBuilder::message(
                        sprintf("'%s(...)' makes no sense here.", $innerFunctionName)
                    )
                    ->identifier('string.normalization.senseless')
                    ->line($firstArg->getStartLine())
                    ->build(),
                ];
            }

            if (!$this->isInnerCaseManipulationFunction($innerFunctionName)) {
                // Special case for ucwords with 2 arguments
                $isTarget = !($innerFunctionName === 'ucwords' && count($innerArgs) > 1);

                if ($isTarget) {
                    return [
                        RuleErrorBuilder::message(
                            sprintf("'%s(...)' makes no sense here.", $innerFunctionName)
                        )
                        ->identifier('string.normalization.senseless')
                        ->line($firstArg->getStartLine())
                        ->build(),
                    ];
                }
            }
        }

        return [];
    }

    private function getFunctionName(FuncCall $node): ?string
    {
        if (!$node->name instanceof Node\Name) {
            return null;
        }

        return strtolower($node->name->toString());
    }

    private function isLengthManipulationFunction(string $functionName): bool
    {
        return in_array($functionName, self::LENGTH_MANIPULATION_FUNCTIONS, true);
    }

    private function isCaseManipulationFunction(string $functionName): bool
    {
        return in_array($functionName, self::CASE_MANIPULATION_FUNCTIONS, true);
    }

    private function isInnerCaseManipulationFunction(string $functionName): bool
    {
        return in_array($functionName, self::INNER_CASE_MANIPULATION_FUNCTIONS, true);
    }

    private function isNonLetterTrimArgument(?Node\Arg $arg): bool
    {
        if ($arg === null) {
            return false;
        }

        $argValue = $arg->value;
        if (!$argValue instanceof Node\Scalar\String_) {
            return false;
        }

        // Check if the trim characters contain letters
        return !preg_match('/[\p{L}]/u', $argValue->value);
    }

    private function getNodeText(Node $node): string
    {
        // Simple text extraction - in a real implementation you might want to use
        // a more sophisticated approach to get the exact source text
        if ($node instanceof Node\Scalar\String_) {
            return "'" . addslashes($node->value) . "'";
        }

        if ($node instanceof Node\Expr\Variable) {
            return '$' . $node->name;
        }

        // For more complex expressions, return a placeholder
        return '...';
    }
}