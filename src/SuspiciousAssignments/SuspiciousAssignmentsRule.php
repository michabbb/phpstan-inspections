<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SuspiciousAssignments;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
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
 * Detects suspicious sequential assignments where variables are immediately overridden without being used.
 *
 * This rule uses function-level analysis to identify patterns like:
 * - $var = getValue1(); $var = getValue2(); // First assignment is wasted
 *
 * It properly handles:
 * - Self-references: $var = $var + 1; (legitimate)
 * - Usage between assignments: $var = getValue1(); echo $var; $var = getValue2(); (legitimate)
 * - Simple consecutive overrides without usage (triggers error)
 *
 * @implements Rule<Node>
 */
class SuspiciousAssignmentsRule implements Rule
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
        // Only analyze function and method bodies for sequential assignments
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return [];
        }

        $statements = $node->stmts;
        if ($statements === null || count($statements) < 2) {
            return [];
        }

        return $this->analyzeSequentialAssignments($statements, $scope);
    }

    /**
     * Analyze statements for sequential variable assignments without usage
     * @param Node\Stmt[] $statements
     * @return list<IdentifierRuleError>
     */
    private function analyzeSequentialAssignments(array $statements, Scope $scope): array
    {
        $errors = [];
        $variableAssignments = [];

        // First pass: collect all variable assignments by statement index
        foreach ($statements as $index => $statement) {
            if (!$statement instanceof Expression || !$statement->expr instanceof Assign) {
                continue;
            }

            $assign = $statement->expr;
            if (!$assign->var instanceof Variable || !is_string($assign->var->name)) {
                continue;
            }

            $varName = $assign->var->name;
            if (!isset($variableAssignments[$varName])) {
                $variableAssignments[$varName] = [];
            }

            $variableAssignments[$varName][] = [
                'index' => $index,
                'statement' => $statement,
                'assign' => $assign,
                'line' => $statement->getStartLine()
            ];
        }

        // Second pass: check for sequential assignments without usage
        foreach ($variableAssignments as $varName => $assignments) {
            if (count($assignments) < 2) {
                continue;
            }

            for ($i = 0; $i < count($assignments) - 1; $i++) {
                $currentAssignment = $assignments[$i];
                $nextAssignment = $assignments[$i + 1];

                // Check if variable is used between these assignments
                $usedBetween = $this->isVariableUsedBetween(
                    $varName,
                    $currentAssignment['index'],
                    $nextAssignment['index'], 
                    $statements
                );

                // Check if variable is used in the RHS of next assignment (self-reference)
                $usedInNextRHS = $this->isVariableUsedInExpression($varName, $nextAssignment['assign']->expr);

                if (!$usedBetween && !$usedInNextRHS) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf(
                            'Variable $%s is assigned on line %d but immediately overridden on line %d without being used.',
                            $varName,
                            $currentAssignment['line'],
                            $nextAssignment['line']
                        )
                    )
                        ->identifier('assignment.sequential')
                        ->line($nextAssignment['line'])
                        ->tip('Remove the unused assignment or use the variable before reassigning.')
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * Check if variable is used between two assignment statement indices
     */
    private function isVariableUsedBetween(string $varName, int $startIndex, int $endIndex, array $statements): bool
    {
        for ($i = $startIndex + 1; $i < $endIndex; $i++) {
            if (isset($statements[$i]) && $this->statementUsesVariable($statements[$i], $varName)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a statement uses the given variable (not as assignment target)
     */
    private function statementUsesVariable(Node $statement, string $varName): bool
    {
        // Skip assignment targets - we only want to find reads/usage
        if ($statement instanceof Expression && $statement->expr instanceof Assign) {
            // Only check the RHS of assignments, not the LHS (target)
            return $this->isVariableUsedInExpression($varName, $statement->expr->expr);
        }

        // For other statements, check entire statement for variable usage
        return $this->isVariableUsedInExpression($varName, $statement);
    }

    /**
     * Check if the variable is used in the given expression
     */
    private function isVariableUsedInExpression(string $varName, Node $expression): bool
    {
        $finder = new NodeFinder();
        $variables = $finder->findInstanceOf($expression, Variable::class);

        foreach ($variables as $var) {
            if (is_string($var->name) && $var->name === $varName) {
                return true;
            }
        }

        return false;
    }
}