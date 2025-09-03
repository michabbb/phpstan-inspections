<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ForEach;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt\For_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Implements ForeachInvariantsInspector from Php Inspections (EA Extended).
 *
 * This rule detects for-loops that can be refactored into foreach-loops for better readability and performance.
 * It identifies patterns where:
 * - A for-loop iterates over an array using an index variable
 * - The loop counter starts at 0
 * - The loop condition uses count() or similar to check array length
 * - Array elements are accessed using the counter variable
 *
 * Such patterns can be safely converted to foreach loops which are more readable and optimized.
 *
 * @implements Rule<For_>
 */
final class ForeachInvariantsRule implements Rule
{
    public const string IDENTIFIER_FOREACH_POSSIBLE = 'foreach.usage.possible';

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return For_::class;
    }

    /**
     * @param For_ $node
     * @return array<int, \PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check if this for-loop can be converted to foreach
        $foreachPossibleErrors = $this->checkForeachPossible($node, $scope);
        $errors = array_merge($errors, $foreachPossibleErrors);

        return $errors;
    }

    /**
     * Check if a for-loop can be converted to foreach.
     */
    private function checkForeachPossible(For_ $forLoop, Scope $scope): array
    {
        $errors = [];

        // Must have exactly one repeated expression (typically increment)
        if (count($forLoop->loop) !== 1) {
            return $errors;
        }

        // Check if the repeated expression is an increment
        $counterVar = $this->getIncrementedVariable($forLoop->loop[0]);
        if ($counterVar === null) {
            return $errors;
        }

        // Check initialization: must start at 0
        if (!$this->isInitializedToZero($forLoop->init, $counterVar)) {
            return $errors;
        }

        // Check condition: must compare counter to array length
        $arrayVar = $this->getArrayFromCondition($forLoop->cond, $counterVar);
        if ($arrayVar === null) {
            return $errors;
        }

        // Check if array is accessed using the counter variable in the loop body
        if (!$this->hasArrayAccessInBody($forLoop->stmts, $counterVar, $arrayVar)) {
            return $errors;
        }

        // If all conditions are met, suggest foreach conversion
        $errors[] = RuleErrorBuilder::message(
            'Foreach can probably be used instead (easier to read and support).'
        )
            ->identifier(self::IDENTIFIER_FOREACH_POSSIBLE)
            ->line($forLoop->getStartLine())
            ->build();

        return $errors;
    }

    /**
     * Extract the variable being incremented from a loop expression.
     */
    private function getIncrementedVariable(Node $expr): ?string
    {
        if ($expr instanceof PostInc || $expr instanceof PreInc) {
            if ($expr->var instanceof Variable && is_string($expr->var->name)) {
                return $expr->var->name;
            }
        }

        return null;
    }

    /**
     * Check if the counter variable is initialized to 0.
     */
    private function isInitializedToZero(array $initExprs, string $counterVar): bool
    {
        foreach ($initExprs as $init) {
            if ($init instanceof Assign) {
                if ($init->var instanceof Variable &&
                    is_string($init->var->name) &&
                    $init->var->name === $counterVar &&
                    $init->expr instanceof LNumber &&
                    $init->expr->value === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract array variable from loop condition that compares counter to count(array).
     */
    private function getArrayFromCondition(array $conditions, string $counterVar): ?string
    {
        if (count($conditions) !== 1) {
            return null;
        }

        $condition = $conditions[0];
        if (!$condition instanceof Smaller) {
            return null;
        }

        // Check both $i < count($array) and count($array) > $i patterns
        $left = $condition->left;
        $right = $condition->right;

        // Pattern: $i < count($array)
        if ($left instanceof Variable &&
            is_string($left->name) &&
            $left->name === $counterVar &&
            $right instanceof FuncCall) {
            return $this->extractArrayFromCountCall($right);
        }

        // Pattern: count($array) > $i
        if ($right instanceof Variable &&
            is_string($right->name) &&
            $right->name === $counterVar &&
            $left instanceof FuncCall) {
            return $this->extractArrayFromCountCall($left);
        }

        return null;
    }

    /**
     * Extract array variable from count() function call.
     */
    private function extractArrayFromCountCall(FuncCall $call): ?string
    {
        if ($call->name instanceof Node\Name &&
            $call->name->toString() === 'count' &&
            count($call->args) === 1) {
            $arg = $call->args[0]->value;
            if ($arg instanceof Variable && is_string($arg->name)) {
                return $arg->name;
            }
        }

        return null;
    }

    /**
     * Check if the loop body contains array access using the counter variable.
     */
    private function hasArrayAccessInBody(array $stmts, string $counterVar, string $arrayVar): bool
    {
        $finder = new NodeFinder();

        $arrayAccesses = $finder->find($stmts, static function (Node $node) use ($counterVar, $arrayVar): bool {
            if (!$node instanceof ArrayDimFetch) {
                return false;
            }

            // Check if accessing the target array
            if (!$node->var instanceof Variable ||
                !is_string($node->var->name) ||
                $node->var->name !== $arrayVar) {
                return false;
            }

            // Check if using counter variable as index
            if (!$node->dim instanceof Variable ||
                !is_string($node->dim->name) ||
                $node->dim->name !== $counterVar) {
                return false;
            }

            return true;
        });

        return count($arrayAccesses) > 0;
    }
}