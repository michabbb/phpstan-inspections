<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects ternary expressions that can be replaced with 'get_debug_type(...)'.
 *
 * This rule identifies patterns like:
 * - is_object($var) ? get_class($var) : gettype($var)
 *
 * And suggests replacing them with:
 * - get_debug_type($var)
 *
 * This improves maintainability by using a more appropriate function.
 *
 * @implements Rule<Ternary>
 */
final class GetDebugTypeCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return Ternary::class;
    }

    /** @param Ternary $node */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Skip short ternary (?:) expressions
        if ($node->if === null) {
            return $errors;
        }

        $condition = $node->cond;
        if ($condition instanceof FuncCall) {
            // Check if condition is is_object($var)
            if ($this->isIsObjectCall($condition)) {
                $trueExpr = $node->if;
                $falseExpr = $node->else;

                if ($trueExpr !== null && $falseExpr !== null) {
                    // Check if true branch is get_class($var) and false branch is gettype($var)
                    if ($this->isGetClassCall($trueExpr, $condition->getArgs()[0]->value)) {
                        if ($this->isGettypeCall($falseExpr, $condition->getArgs()[0]->value)) {
                            // Get the argument from is_object call
                            $argument = $condition->getArgs()[0]->value;

                            // Generate replacement suggestion
                            $replacement = sprintf('get_debug_type(%s)', $this->nodeToString($argument));

                            $errors[] = RuleErrorBuilder::message(sprintf("Can be replaced by '%s' (improves maintainability).", $replacement))
                                ->identifier('debugType.ternaryCanBeReplaced')
                                ->line($node->getStartLine())
                                ->build();
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private function isIsObjectCall(FuncCall $funcCall): bool
    {
        return $funcCall->name instanceof Name
            && $funcCall->name->toString() === 'is_object'
            && count($funcCall->getArgs()) === 1;
    }

    private function isGetClassCall(Node $node, Node $expectedArgument): bool
    {
        $result = false;

        if ($node instanceof FuncCall) {
            if ($node->name instanceof Name && $node->name->toString() === 'get_class') {
                if (count($node->getArgs()) === 1) {
                    $result = $this->areNodesEquivalent($node->getArgs()[0]->value, $expectedArgument);
                }
            }
        }

        return $result;
    }

    private function isGettypeCall(Node $node, Node $expectedArgument): bool
    {
        $result = false;

        if ($node instanceof FuncCall) {
            if ($node->name instanceof Name && $node->name->toString() === 'gettype') {
                if (count($node->getArgs()) === 1) {
                    $result = $this->areNodesEquivalent($node->getArgs()[0]->value, $expectedArgument);
                }
            }
        }

        return $result;
    }

    private function areNodesEquivalent(Node $node1, Node $node2): bool
    {
        $result = false;

        // Simple equivalence check - for more complex cases, you might want to use PHPStan's equivalence utilities
        if ($node1 instanceof Node\Expr\Variable && $node2 instanceof Node\Expr\Variable) {
            $result = $node1->name === $node2->name;
        }

        // For other cases, we could implement more sophisticated checks
        // For now, we'll be conservative and only match simple variables

        return $result;
    }

    private function nodeToString(Node $node): string
    {
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return '$' . $node->name;
        }

        // For more complex expressions, return a generic placeholder
        return '$variable';
    }
}