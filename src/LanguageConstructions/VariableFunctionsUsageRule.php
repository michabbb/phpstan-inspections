<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects variable function usage patterns that can be optimized.
 *
 * This rule identifies calls to call_user_func() and call_user_func_array() that have
 * semantic equivalents using variable function calls or method calls, which are both
 * more readable and potentially faster.
 *
 * The rule detects:
 * - call_user_func_array($callable, array(...)) → call_user_func($callable, ...)
 * - call_user_func($variable, ...) → $variable(...)
 * - call_user_func(['Class', 'method'], ...) → Class::method(...)
 * - Similar patterns for forward_static_call and forward_static_call_array
 *
 * @implements Rule<FuncCall>
 */
final class VariableFunctionsUsageRule implements Rule
{
    /**
     * @var array<string, string>
     */
    private const ARRAY_FUNCTIONS_MAPPING = [
        'call_user_func_array' => 'call_user_func',
        'forward_static_call_array' => 'forward_static_call',
    ];

    /**
     * @var array<string, bool>
     */
    private const CALLER_FUNCTIONS = [
        'call_user_func' => true,
        'forward_static_call' => true,
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

        $functionName = $node->name;
        if (!$functionName instanceof Node\Name) {
            return [];
        }

        $functionNameString = $functionName->toString();

        // Handle call_user_func_array and forward_static_call_array
        if (isset(self::ARRAY_FUNCTIONS_MAPPING[$functionNameString])) {
            return $this->processArrayFunction($node, $functionNameString);
        }

        // Handle call_user_func and forward_static_call
        if (isset(self::CALLER_FUNCTIONS[$functionNameString])) {
            return $this->processCallerFunction($node, $functionNameString, $scope);
        }

        return [];
    }

    /**
     * @param FuncCall $node
     * @param string $functionName
     * @return list<array{RuleError}>
     */
    private function processArrayFunction(FuncCall $node, string $functionName): array
    {
        $args = $node->getArgs();
        if (count($args) !== 2) {
            return [];
        }

        $secondArg = $args[1]->value;
        if (!$secondArg instanceof Array_) {
            return [];
        }

        // Extract array elements
        $arrayElements = $this->extractArrayElements($secondArg);
        if (empty($arrayElements)) {
            return [];
        }

        // Check for references (skip if any element starts with &)
        foreach ($arrayElements as $element) {
            if (str_starts_with($element, '&')) {
                return [];
            }
        }

        // Build replacement
        $firstArgText = $this->getArgText($args[0]->value);
        $elementsText = implode(', ', $arrayElements);
        $replacement = sprintf(
            '%s(%s, %s)',
            self::ARRAY_FUNCTIONS_MAPPING[$functionName],
            $firstArgText,
            $elementsText
        );

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' would make possible to perform better code analysis here.", $replacement)
            )
            ->identifier('function.variableUsage')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    /**
     * @param FuncCall $node
     * @param string $functionName
     * @param Scope $scope
     * @return list<array{RuleError}>
     */
    private function processCallerFunction(FuncCall $node, string $functionName, Scope $scope): array
    {
        $args = $node->getArgs();
        if (empty($args)) {
            return [];
        }

        $firstArg = $args[0]->value;
        $remainingArgs = array_slice($args, 1);

        // Handle array syntax ['Class', 'method']
        if ($firstArg instanceof Array_) {
            return $this->processArrayCallable($node, $functionName, $firstArg, $remainingArgs);
        }

        // Handle variable callable
        if ($firstArg instanceof Variable) {
            return $this->processVariableCallable($node, $functionName, $firstArg, $remainingArgs, $scope);
        }

        // Handle string callable
        if ($firstArg instanceof String_) {
            return $this->processStringCallable($node, $functionName, $firstArg, $remainingArgs);
        }

        return [];
    }

    /**
     * @param FuncCall $node
     * @param string $functionName
     * @param Array_ $arrayCallable
     * @param array<int, Node\Arg> $remainingArgs
     * @return list<array{RuleError}>
     */
    private function processArrayCallable(FuncCall $node, string $functionName, Array_ $arrayCallable, array $remainingArgs): array
    {
        $arrayElements = $this->extractArrayElements($arrayCallable);
        if (count($arrayElements) !== 2) {
            return [];
        }

        [$className, $methodName] = $arrayElements;

        // Skip if class name is a string starting with parent::
        if (str_starts_with($className, "'parent::") || str_starts_with($className, '"parent::')) {
            return [];
        }

        // Handle static method calls
        $useScopeResolution = $functionName === 'forward_static_call';
        $separator = $useScopeResolution ? '::' : '->';

        $argsText = $this->getArgsText($remainingArgs);
        $replacement = sprintf(
            '%s%s%s(%s)',
            $className,
            $separator,
            $methodName,
            $argsText
        );

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' would make more sense here (it also faster).", $replacement)
            )
            ->identifier('function.variableUsage')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    /**
     * @param FuncCall $node
     * @param string $functionName
     * @param Variable $variableCallable
     * @param array<int, Node\Arg> $remainingArgs
     * @param Scope $scope
     * @return list<array{RuleError}>
     */
    private function processVariableCallable(FuncCall $node, string $functionName, Variable $variableCallable, array $remainingArgs, Scope $scope): array
    {
        // Skip if variable name is not a string
        if (!$variableCallable->name instanceof Node\Identifier) {
            return [];
        }

        $variableName = $variableCallable->name->toString();

        // Check variable type - skip if it's a string (would be invalid syntax)
        $variableType = $scope->getType($variableCallable);
        if ($variableType->isString()->yes()) {
            return [];
        }

        $argsText = $this->getArgsText($remainingArgs);
        $replacement = sprintf(
            '$%s(%s)',
            $variableName,
            $argsText
        );

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' would make more sense here (it also faster).", $replacement)
            )
            ->identifier('function.variableUsage')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    /**
     * @param FuncCall $node
     * @param string $functionName
     * @param String_ $stringCallable
     * @param array<int, Node\Arg> $remainingArgs
     * @return list<array{RuleError}>
     */
    private function processStringCallable(FuncCall $node, string $functionName, String_ $stringCallable, array $remainingArgs): array
    {
        $callableName = $stringCallable->value;

        // Skip if callable contains ':' (would be class::method syntax)
        if (str_contains($callableName, ':')) {
            return [];
        }

        // Skip if callable starts with 'parent::'
        if (str_starts_with($callableName, 'parent::')) {
            return [];
        }

        $argsText = $this->getArgsText($remainingArgs);
        $replacement = sprintf(
            '%s(%s)',
            $callableName,
            $argsText
        );

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' would make more sense here (it also faster).", $replacement)
            )
            ->identifier('function.variableUsage')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    /**
     * @param Array_ $array
     * @return list<string>
     */
    private function extractArrayElements(Array_ $array): array
    {
        $elements = [];
        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            $elementText = $this->getArgText($item->value);
            $elements[] = $elementText;
        }
        return $elements;
    }

    /**
     * @param array<int, Node\Arg> $args
     * @return string
     */
    private function getArgsText(array $args): string
    {
        $argTexts = [];
        foreach ($args as $arg) {
            $argTexts[] = $this->getArgText($arg->value);
        }
        return implode(', ', $argTexts);
    }

    /**
     * @param Node $node
     * @return string
     */
    private function getArgText(Node $node): string
    {
        // Handle references (&$var)
        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Arg && $parent->byRef) {
            return '&' . $this->nodeToText($node);
        }

        // Handle variadic (...$args)
        if ($parent instanceof Node\Arg && $parent->unpack) {
            return '...' . $this->nodeToText($node);
        }

        return $this->nodeToText($node);
    }

    /**
     * @param Node $node
     * @return string
     */
    private function nodeToText(Node $node): string
    {
        // Simple text representation - in a real implementation you might want more sophisticated formatting
        if ($node instanceof Variable && $node->name instanceof Node\Identifier) {
            return '$' . $node->name->toString();
        }

        if ($node instanceof String_) {
            return "'" . addslashes($node->value) . "'";
        }

        // For other nodes, return a basic representation
        // This is a simplified version - you might want to use a proper code printer
        return $node->getAttribute('originalContent', (string) $node);
    }
}