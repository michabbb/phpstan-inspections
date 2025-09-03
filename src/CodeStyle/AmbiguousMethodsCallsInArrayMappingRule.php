<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects duplicated method calls in array mapping operations within loops.
 *
 * This rule identifies when the same method is called multiple times within array mapping
 * operations (typically in for/foreach loops), suggesting to extract the call to a local
 * variable for better performance and readability.
 *
 * @implements Rule<Node\Stmt>
 */
class AmbiguousMethodsCallsInArrayMappingRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof For_ && !$node instanceof Foreach_) {
            return [];
        }

        $errors = [];

        // Get the loop body statements
        $statements = $node->stmts;

        foreach ($statements as $statement) {
            if (!$statement instanceof Node\Stmt\Expression) {
                continue;
            }

            $expr = $statement->expr;
            if (!$expr instanceof Assign) {
                continue;
            }

            $assignment = $expr;

            // Check if left side is an array access
            if (!$assignment->var instanceof ArrayDimFetch) {
                continue;
            }

            $arrayAccess = $assignment->var;
            $value = $assignment->expr;

            // Find calls (methods/functions) in the array access expression (left side)
            $leftMethodCalls = $this->findMethodCalls($arrayAccess);

            if (empty($leftMethodCalls)) {
                continue;
            }

            // Find calls (methods/functions) in the value expression (right side)
            $rightMethodCalls = $this->findMethodCalls($value);

            // Check for duplicated calls (methods or functions)
            foreach ($rightMethodCalls as $rightCall) {
                foreach ($leftMethodCalls as $leftCall) {
                    if ($this->areMethodCallsEqual($leftCall, $rightCall)) {
                        $errors[] = RuleErrorBuilder::message(
                            'Duplicated method calls should be moved to a local variable.'
                        )->identifier('methodCall.duplicate')->line($rightCall->getLine())->build();
                        break 2; // Only report once per assignment
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Find all method and function calls in an expression tree
     *
     * @return array
     */
    private function findMethodCalls(Node $node): array
    {
        $calls = [];

        if ($node instanceof MethodCall || $node instanceof FuncCall) {
            $calls[] = $node;
        }

        foreach ($node->getSubNodeNames() as $subNodeName) {
            $subNode = $node->{$subNodeName};
            if ($subNode instanceof Node) {
                $calls = array_merge($calls, $this->findMethodCalls($subNode));
            } elseif (is_array($subNode)) {
                foreach ($subNode as $item) {
                    if ($item instanceof Node) {
                        $calls = array_merge($calls, $this->findMethodCalls($item));
                    }
                }
            }
        }

        return $calls;
    }

    /**
     * Check if two calls (method or function) are equivalent
     */
    private function areMethodCallsEqual($call1, $call2): bool
    {
        // Handle method calls
        if ($call1 instanceof MethodCall && $call2 instanceof MethodCall) {
            // Check if method names are the same
            if (!$call1->name instanceof Node\Identifier || !$call2->name instanceof Node\Identifier) {
                return false;
            }

            if ($call1->name->name !== $call2->name->name) {
                return false;
            }

            // Check if the variable/expression being called on is the same
            return $this->areExpressionsEqual($call1->var, $call2->var);
        }

        // Handle function calls
        if ($call1 instanceof FuncCall && $call2 instanceof FuncCall) {
            // Check if function names are the same
            if (!$call1->name instanceof Node\Name || !$call2->name instanceof Node\Name) {
                return false;
            }

            if ($call1->name->toString() !== $call2->name->toString()) {
                return false;
            }

            // Functions don't have a "var" - they're standalone
            return true;
        }

        // Mixed types are not equal
        return false;
    }

    /**
     * Check if two expressions are structurally equal
     */
    private function areExpressionsEqual(Node $expr1, Node $expr2): bool
    {
        if (get_class($expr1) !== get_class($expr2)) {
            return false;
        }

        if ($expr1 instanceof Variable && $expr2 instanceof Variable) {
            return $expr1->name === $expr2->name;
        }

        if ($expr1 instanceof Node\Expr\PropertyFetch && $expr2 instanceof Node\Expr\PropertyFetch) {
            return $this->areExpressionsEqual($expr1->var, $expr2->var) &&
                   $expr1->name instanceof Node\Identifier &&
                   $expr2->name instanceof Node\Identifier &&
                   $expr1->name->name === $expr2->name->name;
        }

        // For more complex expressions, we could add more checks here
        // For now, we'll do a simple comparison
        return $expr1 === $expr2;
    }
}
