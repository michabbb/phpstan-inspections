<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SuspiciousAssignments;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\IdentifierRuleError;

/**
 * Detects variables that are only used inside closures via the `use` clause.
 *
 * This rule identifies variables that are:
 * - Assigned outside a closure
 * - Passed to a closure via the `use` clause
 * - Only used inside that closure (not used anywhere else in the function)
 *
 * Such variables could be defined directly inside the closure to reduce
 * unnecessary scope pollution.
 *
 * @implements Rule<Node>
 */
class VariableOnlyUsedInClosureRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only analyze function and method bodies
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return [];
        }

        $statements = $node->stmts;
        if ($statements === null || empty($statements)) {
            return [];
        }

        return $this->analyzeVariableUsageInClosures($statements);
    }

    /**
     * Analyze statements for variables only used in closures
     * @param Node\Stmt[] $statements
     * @return list<IdentifierRuleError>
     */
    private function analyzeVariableUsageInClosures(array $statements): array
    {
        $errors = [];
        $nodeFinder = new NodeFinder();

        // Find all variable assignments
        $assignments = $this->findVariableAssignments($statements);

        // Find all closures with use clauses
        $closures = $nodeFinder->findInstanceOf($statements, Closure::class);

        foreach ($assignments as $varName => $assignment) {
            $isOnlyUsedInClosure = $this->isVariableOnlyUsedInClosures(
                $varName,
                $assignment,
                $closures,
                $statements
            );

            if ($isOnlyUsedInClosure) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'Variable $%s is only used inside closure. Consider defining it inside the closure instead.',
                        $varName
                    )
                )
                    ->identifier('variable.onlyUsedInClosure')
                    ->line($assignment['line'])
                    ->tip('Move the variable declaration inside the closure to reduce scope pollution.')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * Find all variable assignments in statements
     * @param Node\Stmt[] $statements
     * @return array<string, array{line: int, node: Node}>
     */
    private function findVariableAssignments(array $statements): array
    {
        $assignments = [];

        foreach ($statements as $statement) {
            if (!$statement instanceof Expression || !$statement->expr instanceof Assign) {
                continue;
            }

            $assign = $statement->expr;
            if (!$assign->var instanceof Variable || !is_string($assign->var->name)) {
                continue;
            }

            $varName = $assign->var->name;
            $assignments[$varName] = [
                'line' => $statement->getStartLine(),
                'node' => $statement
            ];
        }

        return $assignments;
    }

    /**
     * Check if a variable is only used inside closures
     * @param Closure[] $closures
     * @param Node\Stmt[] $statements
     */
    private function isVariableOnlyUsedInClosures(
        string $varName,
        array $assignment,
        array $closures,
        array $statements
    ): bool {
        $nodeFinder = new NodeFinder();

        // Check if variable is used in any closure's use clause
        $usedInClosureUse = false;
        $closuresUsingVariable = [];

        foreach ($closures as $closure) {
            if ($closure->uses === null) {
                continue;
            }

            foreach ($closure->uses as $use) {
                if ($use->var instanceof Variable &&
                    is_string($use->var->name) &&
                    $use->var->name === $varName) {
                    $usedInClosureUse = true;
                    $closuresUsingVariable[] = $closure;
                    break;
                }
            }
        }

        // If not used in any closure's use clause, it's not our case
        if (!$usedInClosureUse) {
            return false;
        }

        // If variable is used in multiple closures, it makes sense to keep it outside
        // to avoid code duplication - don't flag this case
        if (count($closuresUsingVariable) > 1) {
            return false;
        }

        // Check if variable is used anywhere outside the closures that use it
        $allVariableUsages = $nodeFinder->find($statements, static function (Node $node) use ($varName): bool {
            return $node instanceof Variable &&
                   is_string($node->name) &&
                   $node->name === $varName;
        });

        foreach ($allVariableUsages as $usage) {
            // Skip the assignment itself
            if ($this->isNodeInside($usage, $assignment['node'])) {
                continue;
            }

            // Skip usages inside closures that use this variable
            $isInsideTargetClosure = false;
            foreach ($closuresUsingVariable as $closure) {
                if ($this->isNodeInside($usage, $closure)) {
                    $isInsideTargetClosure = true;
                    break;
                }
            }

            // If we find usage outside target closures, the variable is used elsewhere
            if (!$isInsideTargetClosure) {
                return false;
            }
        }

        // Variable is only used in closures
        return true;
    }

    /**
     * Check if a node is inside another node
     */
    private function isNodeInside(Node $needle, Node $haystack): bool
    {
        $nodeFinder = new NodeFinder();
        $found = $nodeFinder->findFirst($haystack, static function (Node $node) use ($needle): bool {
            return $node === $needle;
        });

        return $found !== null;
    }
}