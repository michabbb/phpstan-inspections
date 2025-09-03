<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\For_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects slow array operations used in loops that can hurt performance.
 * 
 * Based on EA Extended SlowArrayOperationsInLoopInspector.java
 * 
 * @implements Rule<Node>
 */
final class SlowArrayOperationsInLoopRule implements Rule
{
    private const array GREEDY_FUNCTIONS = [
        'array_merge',
        'array_merge_recursive', 
        'array_replace',
        'array_replace_recursive'
    ];

    private const array SLOW_FUNCTIONS = [
        'count',
        'sizeof', // alias for count
        'strlen',
        'mb_strlen'
    ];

    private const string MESSAGE_GREEDY = "'%s(...)' is used in a loop and is a resources greedy construction.";
    private const string MESSAGE_SLOW = "'%s(...)' is used in a loop and is a low performing construction.";

    public function getNodeType(): string
    {
        return Node::class;
    }

    /** 
     * @param Node $node 
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check for slow function calls in for loop conditions
        if ($node instanceof For_) {
            $errors = [...$errors, ...$this->checkSlowFunctionsInForLoop($node, $scope)];
        }

        // Check for greedy function calls in loop statements
        if ($node instanceof Node\Stmt\Foreach_ || $node instanceof Node\Stmt\While_ || $node instanceof Node\Stmt\For_) {
            $errors = [...$errors, ...$this->checkGreedyFunctionsInLoop($node, $scope)];
        }

        return $errors;
    }

    /** @return list<\PHPStan\Rules\IdentifierRuleError> */
    private function checkGreedyFunctionsInLoop(Node $loopNode, Scope $scope): array
    {
        $errors = [];
        
        // Get the statements inside the loop
        $statements = [];
        if ($loopNode instanceof Node\Stmt\Foreach_) {
            $statements = $loopNode->stmts;
        } elseif ($loopNode instanceof Node\Stmt\While_) {
            $statements = $loopNode->stmts;
        } elseif ($loopNode instanceof Node\Stmt\For_) {
            $statements = $loopNode->stmts;
        }

        // Traverse all statements in the loop looking for assignments with greedy functions
        foreach ($statements as $stmt) {
            if ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Assign) {
                $assignment = $stmt->expr;
                $funcCall = $assignment->expr;
                
                if (!$funcCall instanceof FuncCall || !$funcCall->name instanceof Node\Name) {
                    continue;
                }

                $functionName = $funcCall->name->toString();
                if (!in_array($functionName, self::GREEDY_FUNCTIONS, true)) {
                    continue;
                }

                $args = $funcCall->getArgs();
                if (count($args) <= 1) {
                    continue;
                }

                // Check if assignment target matches one of the function arguments (self-assignment pattern)
                $assignmentTarget = $assignment->var;
                foreach ($args as $arg) {
                    if ($this->nodesAreEquivalent($assignmentTarget, $arg->value)) {
                        $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_GREEDY, $functionName))
                            ->identifier('performance.greedyArrayOperation')
                            ->tip('Consider collecting values in an array first, then merge once outside the loop')
                            ->line($funcCall->getStartLine())
                            ->build();
                        break; // Found one match, no need to check other arguments
                    }
                }
            }
        }

        return $errors;
    }

    /** @return list<\PHPStan\Rules\IdentifierRuleError> */
    private function checkSlowFunctionsInForLoop(For_ $forLoop, Scope $scope): array
    {
        $errors = [];

        foreach ($forLoop->cond as $condition) {
            if (!$condition instanceof Node\Expr\BinaryOp) {
                continue;
            }

            $funcCall = null;
            if ($condition->left instanceof FuncCall) {
                $funcCall = $condition->left;
            } elseif ($condition->right instanceof FuncCall) {
                $funcCall = $condition->right;
            }

            if ($funcCall === null || !$funcCall->name instanceof Node\Name) {
                continue;
            }

            $functionName = $funcCall->name->toString();
            if (in_array($functionName, self::SLOW_FUNCTIONS, true)) {
                $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_SLOW, $functionName))
                    ->identifier('performance.slowFunctionInLoop')
                    ->tip('Cache the result in a variable before the loop starts')
                    ->line($condition->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }


    /**
     * Simple node equivalence check.
     * This is a basic implementation - in production you might want more sophisticated comparison.
     */
    private function nodesAreEquivalent(Node $node1, Node $node2): bool
    {
        $equivalent = false;
        
        if (get_class($node1) === get_class($node2)) {
            // For variables, compare names
            if ($node1 instanceof Node\Expr\Variable && $node2 instanceof Node\Expr\Variable) {
                $equivalent = $node1->name === $node2->name;
            } elseif ($node1 instanceof Node\Expr\PropertyFetch && $node2 instanceof Node\Expr\PropertyFetch) {
                // For property access, compare object and property
                $equivalent = $this->nodesAreEquivalent($node1->var, $node2->var) && 
                             $node1->name === $node2->name;
            } elseif ($node1 instanceof Node\Expr\ArrayDimFetch && $node2 instanceof Node\Expr\ArrayDimFetch) {
                // For array access, compare variable and dimension  
                $varsEquivalent = $this->nodesAreEquivalent($node1->var, $node2->var);
                
                if ($node1->dim === null && $node2->dim === null) {
                    $equivalent = $varsEquivalent;
                } elseif ($node1->dim !== null && $node2->dim !== null) {
                    $equivalent = $varsEquivalent && $this->nodesAreEquivalent($node1->dim, $node2->dim);
                }
            }
        }
        
        return $equivalent;
    }
}