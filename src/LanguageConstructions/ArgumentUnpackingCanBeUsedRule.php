<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayCreation;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects call_user_func_array() calls that can be replaced with argument unpacking syntax.
 *
 * This rule identifies when call_user_func_array() is used with a string literal function name
 * and suggests using the more efficient and cleaner argument unpacking syntax (...$args).
 * The unpacking syntax is 3x+ faster and less magical than call_user_func_array().
 *
 * @implements Rule<FuncCall>
 */
class ArgumentUnpackingCanBeUsedRule implements Rule
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

        // Check if this is a call_user_func_array call
        if (!$node->name instanceof Name || $node->name->toString() !== 'call_user_func_array') {
            return [];
        }

        // Must have exactly 2 arguments
        if (count($node->args) !== 2) {
            return [];
        }

        $firstArg = $node->args[0]->value;
        $secondArg = $node->args[1]->value;

        // First argument must be a string literal
        if (!$firstArg instanceof String_) {
            return [];
        }

        // Second argument must be a valid container
        if (!$this->isValidContainer($secondArg)) {
            return [];
        }

        $functionName = $firstArg->value;
        $secondArgText = $this->getExpressionText($secondArg);
        $replacement = $functionName . '(...' . $secondArgText . ')';

        return [
            RuleErrorBuilder::message(
                sprintf(
                    "'%s' would make more sense here (3x+ faster). Use '...array_values(...)' for unpacking associative arrays.",
                    $replacement
                )
            )
            ->identifier('functionCall.argumentUnpacking')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    /**
     * Check if the expression is a valid container for argument unpacking
     */
    private function isValidContainer(Node $node): bool
    {
        return $node instanceof Variable
            || $node instanceof PropertyFetch
            || $node instanceof StaticPropertyFetch
            || $node instanceof ArrayCreation
            || $node instanceof FuncCall;
    }

    /**
     * Get a textual representation of the expression for the replacement suggestion
     */
    private function getExpressionText(Node $node): string
    {
        // For simple cases, we can reconstruct the text
        if ($node instanceof Variable) {
            return '$' . $node->name;
        }

        if ($node instanceof PropertyFetch) {
            $varText = $this->getExpressionText($node->var);
            $propName = $node->name instanceof Node\Identifier ? $node->name->name : '/* property */';
            return $varText . '->' . $propName;
        }

        if ($node instanceof StaticPropertyFetch) {
            $classText = $node->class instanceof Name ? $node->class->toString() : '/* class */';
            $propName = $node->name instanceof Node\VarLikeIdentifier ? $node->name->name : '/* property */';
            return $classText . '::$' . $propName;
        }

        if ($node instanceof ArrayCreation) {
            return '/* array */';
        }

        if ($node instanceof FuncCall) {
            $funcName = $node->name instanceof Name ? $node->name->toString() : '/* function */';
            return $funcName . '(/* args */)';
        }

        return '/* expression */';
    }
}