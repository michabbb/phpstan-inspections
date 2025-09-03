<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Closure;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects when 'list(...) = ' usage is possible instead of multiple assignments.
 *
 * This rule identifies:
 * - Multiple consecutive assignments to array elements that can be replaced with list() destructuring
 * - Foreach loops where list() assignment can be moved to the foreach declaration
 *
 * Examples:
 * - $first = $array[0]; $second = $array[1]; → list($first, $second) = $array;
 * - foreach ($items as $item) { list($a, $b) = $item; } → foreach ($items as list($a, $b))
 *
 * @implements Rule<Node>
 */
class MultiAssignmentUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Handle functions, methods, closures - check for consecutive assignments
        if ($node instanceof ClassMethod || $node instanceof Function_ || $node instanceof Closure) {
            $consecutiveErrors = $this->checkConsecutiveAssignmentsInFunction($node);
            $errors = array_merge($errors, $consecutiveErrors);
        }

        // Handle foreach list usage (for Foreach_ nodes)
        if ($node instanceof Foreach_) {
            $foreachErrors = $this->checkForeachListUsage($node);
            $errors = array_merge($errors, $foreachErrors);
        }

        return $errors;
    }

    /**
     * Check for consecutive assignments within a function/method/closure.
     */
    private function checkConsecutiveAssignmentsInFunction(Node $functionNode): array
    {
        $errors = [];

        // Get statements from the function node
        $stmts = [];
        if ($functionNode instanceof ClassMethod || $functionNode instanceof Function_) {
            $stmts = $functionNode->stmts ?? [];
        } elseif ($functionNode instanceof Closure) {
            $stmts = $functionNode->stmts;
        }

        $errors = array_merge($errors, $this->findConsecutiveAssignments($stmts));

        return $errors;
    }

    /**
     * Find consecutive array assignments in a list of statements.
     * @param array<int, Node> $statements
     * @return array<int, \PHPStan\Rules\IdentifierRuleError>
     */
    private function findConsecutiveAssignments(array $statements): array
    {
        $errors = [];

        for ($i = 1; $i < count($statements); $i++) {
            $currentStmt = $statements[$i];
            $previousStmt = $statements[$i - 1];

            // Both must be assignment expressions
            if (!$currentStmt instanceof Expression || !$currentStmt->expr instanceof Assign ||
                !$previousStmt instanceof Expression || !$previousStmt->expr instanceof Assign) {
                continue;
            }

            $currentAssign = $currentStmt->expr;
            $previousAssign = $previousStmt->expr;

            // Check if both assignments are to array elements of the same variable
            $currentContainer = $this->getArrayAccessContainer($currentAssign->var);
            $previousContainer = $this->getArrayAccessContainer($previousAssign->var);

            if ($currentContainer === null || $previousContainer === null) {
                continue;
            }

            // Check if containers are the same variable
            if (!$this->areSameVariable($currentContainer, $previousContainer)) {
                continue;
            }

            // Check if indices are numeric literals
            $currentIndexValue = $this->getNumericIndex($currentAssign->var);
            $previousIndexValue = $this->getNumericIndex($previousAssign->var);

            if ($currentIndexValue === null || $previousIndexValue === null) {
                continue;
            }

            // Check if indices are consecutive (previous + 1 = current)
            if ($previousIndexValue + 1 !== $currentIndexValue) {
                continue;
            }

            $containerName = $this->getVariableName($currentContainer);
            if ($containerName === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(
                "Perhaps 'list(...) = \${$containerName}' can be used instead (check similar statements)."
            )
                ->identifier('assignment.multiToList')
                ->line($currentStmt->getStartLine())
                ->tip('Consider using list() destructuring for multiple array element assignments')
                ->build();

            // Only report once per sequence to avoid spam
            break;
        }

        return $errors;
    }

    /**
     * Check for foreach loops where list() assignment can be moved to the foreach declaration.
     */
    private function checkForeachListUsage(Foreach_ $foreach): array
    {
        $errors = [];

        // Get the variable used in foreach
        $foreachVar = $foreach->valueVar;
        if (!$foreachVar instanceof Variable) {
            return $errors;
        }

        $foreachVarName = $this->getVariableName($foreachVar);
        if ($foreachVarName === null) {
            return $errors;
        }

        // Find list() assignments in the foreach body that use the same variable
        $nodeFinder = new NodeFinder();
        $listAssignments = $nodeFinder->find($foreach->stmts, static function (Node $node) {
            return $node instanceof Assign
                && $node->var instanceof List_
                && $node->expr instanceof Variable;
        });

        foreach ($listAssignments as $listAssign) {
            /** @var Assign $listAssign */
            /** @var Variable $assignedFrom */
            $assignedFrom = $listAssign->expr;

            // Check if the assigned variable matches the foreach variable
            if ($this->getVariableName($assignedFrom) === $foreachVarName) {
                $errors[] = RuleErrorBuilder::message(
                    "foreach (... as list(...)) could be used instead."
                )
                    ->identifier('foreach.listUsage')
                    ->line($listAssign->getStartLine())
                    ->tip('Consider moving list() destructuring to the foreach declaration')
                    ->build();
                break; // Only report once per foreach
            }
        }

        return $errors;
    }

    /**
     * Extract the container variable from an array access expression.
     */
    private function getArrayAccessContainer(Node $node): ?Variable
    {
        if (!$node instanceof ArrayDimFetch) {
            return null;
        }

        $container = $node->var;
        while ($container instanceof ArrayDimFetch) {
            $container = $container->var;
        }

        return $container instanceof Variable ? $container : null;
    }

    /**
     * Get the numeric index from an array access expression.
     */
    private function getNumericIndex(Node $node): ?int
    {
        if (!$node instanceof ArrayDimFetch) {
            return null;
        }

        $dim = $node->dim;
        if (!$dim instanceof Node\Scalar\LNumber) {
            return null;
        }

        return $dim->value;
    }

    /**
     * Check if two variables refer to the same variable.
     */
    private function areSameVariable(Variable $var1, Variable $var2): bool
    {
        $name1 = $this->getVariableName($var1);
        $name2 = $this->getVariableName($var2);

        return $name1 !== null && $name2 !== null && $name1 === $name2;
    }

    /**
     * Get the name of a variable.
     */
    private function getVariableName(Variable $variable): ?string
    {
        return is_string($variable->name) ? $variable->name : null;
    }
}