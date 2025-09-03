<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unnecessary string case manipulation before case-sensitive string position functions.
 *
 * This rule identifies when strpos(), mb_strpos(), strrpos(), or mb_strrpos() are called
 * with arguments that are wrapped in strtolower(), mb_strtolower(), strtoupper(), or mb_strtoupper().
 * In such cases, the case-insensitive versions (stripos(), mb_stripos(), strripos(), mb_strripos())
 * should be used instead for better performance and clarity.
 *
 * @implements Rule<FuncCall>
 */
final class StringCaseManipulationRule implements Rule
{
    private const CASE_SENSITIVE_FUNCTIONS = [
        'strpos' => 'stripos',
        'mb_strpos' => 'mb_stripos',
        'strrpos' => 'strripos',
        'mb_strrpos' => 'mb_strripos',
    ];

    private const CASE_MANIPULATION_FUNCTIONS = [
        'strtolower',
        'mb_strtolower',
        'strtoupper',
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

        $functionName = $this->resolveFunctionName($node, $scope);
        if ($functionName === null || !isset(self::CASE_SENSITIVE_FUNCTIONS[$functionName])) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 2) {
            return [];
        }

        $firstArg = $args[0]->value;
        $secondArg = $args[1]->value;

        $hasCaseManipulation = false;
        $caseManipulationArg = null;

        // Check if first argument is a case manipulation function
        if ($this->isCaseManipulationFunction($firstArg, $scope)) {
            $hasCaseManipulation = true;
            $caseManipulationArg = 0;
        }

        // Check if second argument is a case manipulation function
        if ($this->isCaseManipulationFunction($secondArg, $scope)) {
            $hasCaseManipulation = true;
            $caseManipulationArg = 1;
        }

        if (!$hasCaseManipulation) {
            return [];
        }

        $replacementFunction = self::CASE_SENSITIVE_FUNCTIONS[$functionName];

        // Build the suggested replacement call
        $suggestedCall = $this->buildSuggestedCall($node, $replacementFunction, $caseManipulationArg);

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' should be used instead.", $suggestedCall)
            )
                ->identifier('string.caseManipulation')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function resolveFunctionName(FuncCall $funcCall, Scope $scope): ?string
    {
        if (!$funcCall->name instanceof Node\Name) {
            return null;
        }

        return $funcCall->name->toString();
    }

    private function isCaseManipulationFunction(Node $node, Scope $scope): bool
    {
        if (!$node instanceof FuncCall) {
            return false;
        }

        $functionName = $this->resolveFunctionName($node, $scope);
        if ($functionName === null) {
            return false;
        }

        return in_array($functionName, self::CASE_MANIPULATION_FUNCTIONS, true);
    }

    private function buildSuggestedCall(FuncCall $originalCall, string $replacementFunction, ?int $caseManipulationArg): string
    {
        $args = $originalCall->getArgs();

        if ($caseManipulationArg === 0) {
            // First argument has case manipulation - unwrap it
            $innerArg = $args[0]->value;
            if ($innerArg instanceof FuncCall && count($innerArg->getArgs()) === 1) {
                $unwrappedFirst = $this->prettyPrintExpr($innerArg->getArgs()[0]->value);
            } else {
                $unwrappedFirst = $this->prettyPrintExpr($args[0]->value);
            }
            $second = $this->prettyPrintExpr($args[1]->value);
        } elseif ($caseManipulationArg === 1) {
            // Second argument has case manipulation - unwrap it
            $first = $this->prettyPrintExpr($args[0]->value);
            $innerArg = $args[1]->value;
            if ($innerArg instanceof FuncCall && count($innerArg->getArgs()) === 1) {
                $unwrappedSecond = $this->prettyPrintExpr($innerArg->getArgs()[0]->value);
            } else {
                $unwrappedSecond = $this->prettyPrintExpr($args[1]->value);
            }
        } else {
            // Both arguments have case manipulation - unwrap both
            $innerFirst = $args[0]->value;
            $innerSecond = $args[1]->value;

            if ($innerFirst instanceof FuncCall && count($innerFirst->getArgs()) === 1) {
                $unwrappedFirst = $this->prettyPrintExpr($innerFirst->getArgs()[0]->value);
            } else {
                $unwrappedFirst = $this->prettyPrintExpr($args[0]->value);
            }

            if ($innerSecond instanceof FuncCall && count($innerSecond->getArgs()) === 1) {
                $unwrappedSecond = $this->prettyPrintExpr($innerSecond->getArgs()[0]->value);
            } else {
                $unwrappedSecond = $this->prettyPrintExpr($args[1]->value);
            }
        }

        return sprintf('%s(%s, %s)', $replacementFunction, $unwrappedFirst ?? '', $unwrappedSecond ?? '');
    }

    private function prettyPrintExpr(Node $node): string
    {
        // Simple pretty printing for expressions
        if ($node instanceof Node\Scalar\String_) {
            return "'" . addslashes($node->value) . "'";
        }
        if ($node instanceof Node\Expr\Variable) {
            return '$' . $node->name;
        }
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $args = array_map(fn($arg) => $this->prettyPrintExpr($arg->value), $node->getArgs());
            return $node->name->toString() . '(' . implode(', ', $args) . ')';
        }

        // Fallback - this is a simplified implementation
        // In a real-world scenario, you might want to use PHP-Parser's Standard pretty printer
        return '...';
    }
}