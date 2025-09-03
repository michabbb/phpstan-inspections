<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ForEach;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Return_;

use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Implements DisconnectedForeachInstructionInspector from Php Inspections (EA Extended).
 *
 * This rule detects statements within foreach loops that seem to be disconnected from the loop's purpose.
 * It identifies statements that don't depend on variables modified within the loop and are not performing
 * certain types of operations (assignments, object creation, etc.) that are typically expected in loops.
 *
 * The rule helps identify potentially misplaced code or statements that might be better placed outside the loop.
 *
 * @implements Rule<Foreach_>
 */
final class DisconnectedForeachInstructionRule implements Rule
{
    public const string IDENTIFIER_DISCONNECTED_STATEMENT = 'foreach.disconnectedStatement';



    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Foreach_::class;
    }

/**
 * @param Foreach_ $node
 * @return list<\PHPStan\Rules\IdentifierRuleError>
 */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Get the foreach body statements
        $bodyStatements = $this->getForeachBodyStatements($node);
        if (count($bodyStatements) === 0) {
            return $errors;
        }

        // Collect variables that are modified within this and outer loops
        $modifiedVariables = $this->collectModifiedVariables($node, $scope);

        // Analyze each statement in the foreach body
        foreach ($bodyStatements as $statement) {


            if ($this->shouldAnalyzeStatement($statement)) {
                $statementErrors = $this->analyzeStatement($statement, $modifiedVariables, $scope);
                $errors = array_merge($errors, $statementErrors);
            }
        }

        return $errors;
    }

    /**
     * Get the statements from the foreach body.
     * @return list<Node>
     */
    private function getForeachBodyStatements(Foreach_ $foreach): array
    {
        $statements = [];

        if ($foreach->stmts !== null) {
            foreach ($foreach->stmts as $stmt) {
                if ($stmt instanceof Node) {
                    $statements[] = $stmt;
                }
            }
        }

        return $statements;
    }

    /**
     * Collect all variables that are modified within this foreach and any outer loops.
     * @return list<string>
     */
    private function collectModifiedVariables(Foreach_ $foreach, Scope $scope): array
    {
        $modifiedVariables = [];

        // Add variables from current foreach (these are modified by the loop itself)
        if ($foreach->valueVar instanceof Variable && is_string($foreach->valueVar->name)) {
            $modifiedVariables[] = $foreach->valueVar->name;
        }
        if ($foreach->keyVar instanceof Variable && is_string($foreach->keyVar->name)) {
            $modifiedVariables[] = $foreach->keyVar->name;
        }

        // Find variables that are assigned within the foreach body
        $finder = new NodeFinder();
        $assignments = $finder->find($foreach->stmts, static function (Node $node): bool {
            return $node instanceof Assign;
        });

        foreach ($assignments as $assignment) {
            if ($assignment instanceof Assign && $assignment->var instanceof Variable && is_string($assignment->var->name)) {
                $modifiedVariables[] = $assignment->var->name;
            }
        }

        // Find variables that are incremented/decremented within the foreach body
        $incrementDecrements = $finder->find($foreach->stmts, static function (Node $node): bool {
            return $node instanceof PreInc || $node instanceof PreDec || 
                   $node instanceof PostInc || $node instanceof PostDec;
        });

        foreach ($incrementDecrements as $incDec) {
            if ($incDec->var instanceof Variable && is_string($incDec->var->name)) {
                $modifiedVariables[] = $incDec->var->name;
            }
        }

        return array_values(array_unique($modifiedVariables));
    }

    /**
     * Collect variables from outer scope that are used within the foreach.
     * This is a simplified implementation.
     * @return list<string>
     */
    private function collectOuterScopeVariables(Foreach_ $foreach, Scope $scope): array
    {
        $outerVariables = [];

        // Find all variables used in the foreach body
        $finder = new NodeFinder();
        $variables = $finder->find($foreach->stmts, static function (Node $node): bool {
            return $node instanceof Variable;
        });

        // Get loop variables
        $loopVariables = [];
        if ($foreach->valueVar instanceof Variable && is_string($foreach->valueVar->name)) {
            $loopVariables[] = $foreach->valueVar->name;
        }
        if ($foreach->keyVar instanceof Variable && is_string($foreach->keyVar->name)) {
            $loopVariables[] = $foreach->keyVar->name;
        }

        // Collect variables that are not loop variables
        foreach ($variables as $variable) {
            if ($variable instanceof Variable && is_string($variable->name)) {
                if (!in_array($variable->name, $loopVariables, true)) {
                    $outerVariables[] = $variable->name;
                }
            }
        }

        return array_values(array_unique($outerVariables));
    }

    /**
     * Check if a statement should be analyzed for disconnection.
     */
    private function shouldAnalyzeStatement(Node $statement): bool
    {
        // Skip comments and empty statements
        return !($statement instanceof Node\Stmt\Nop) &&
               !($statement instanceof Node\Stmt\InlineHTML);
    }

    /**
     * Analyze a single statement to determine if it's disconnected.
     * @param list<string> $modifiedVariables
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function analyzeStatement(Node $statement, array $modifiedVariables, Scope $scope): array
    {
        $errors = [];

        // Check if the statement depends on any modified variables
        $dependencies = $this->collectStatementDependencies($statement);
        $hasDependency = count(array_intersect($dependencies, $modifiedVariables)) > 0;

        // Only analyze statements that use variables but don't depend on loop variables
        if (!$hasDependency && count($dependencies) > 0) {
            // Check if this is an allowed expression type
            $expressionType = $this->getExpressionType($statement);

            if (!$this->isAllowedExpressionType($expressionType)) {
                // Additional checks for control flow
                if ($this->shouldReportStatement($statement)) {
                    $errors[] = RuleErrorBuilder::message(
                        'This statement seems to be disconnected from its parent foreach.'
                    )
                        ->identifier(self::IDENTIFIER_DISCONNECTED_STATEMENT)
                        ->line($statement->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * Collect all variables that this statement depends on.
     * @return list<string>
     */
    private function collectStatementDependencies(Node $statement): array
    {
        $dependencies = [];

        $finder = new NodeFinder();
        $variables = $finder->find($statement, static function (Node $node): bool {
            return $node instanceof Variable;
        });

        foreach ($variables as $variable) {
            if ($variable instanceof Variable && is_string($variable->name)) {
                $dependencies[] = $variable->name;
            }
        }



        return array_values(array_unique($dependencies));
    }

    /**
     * Determine the type of expression for a statement.
     */
    private function getExpressionType(Node $statement): string
    {
        // Handle statement wrappers
        if ($statement instanceof Node\Stmt\Expression) {
            $statement = $statement->expr;
        }

        // Handle different expression types
        if ($statement instanceof New_) {
            return 'NEW';
        }

        if ($statement instanceof Assign) {
            if ($statement->var instanceof ArrayDimFetch) {
                return 'ARRAY_ACCUMULATE';
            }
            return 'ASSIGNMENT';
        }

        if ($statement instanceof Clone_) {
            return 'CLONE';
        }

        if ($statement instanceof PreInc || $statement instanceof PreDec ||
            $statement instanceof PostInc || $statement instanceof PostDec) {
            return 'INCREMENT_DECREMENT';
        }

        if ($statement instanceof MethodCall) {
            // Check for DOM element creation
            if ($this->isDomElementCreation($statement)) {
                return 'DOM_ELEMENT_CREATE';
            }
            // All other method calls are legitimate in foreach loops
            return 'METHOD_CALL';
        }

        if ($statement instanceof Break_ || $statement instanceof Continue_ ||
            $statement instanceof Return_) {
            return 'CONTROL_STATEMENT';
        }

        return 'OTHER';
    }

    /**
     * Check if a method call is DOM element creation.
     */
    private function isDomElementCreation(MethodCall $methodCall): bool
    {
        if (!$methodCall->name instanceof Node\Identifier ||
            $methodCall->name->name !== 'createElement') {
            return false;
        }

        // Check if the variable is a DOMDocument instance
        if ($methodCall->var instanceof Variable) {
            // This is a simplified check - in practice, you'd need scope analysis
            // to determine if the variable is a DOMDocument
            return true;
        }

        return false;
    }

    /**
     * Check if the expression type is allowed (should not be reported).
     */
    private function isAllowedExpressionType(string $type): bool
    {
        return in_array($type, [
            'NEW',
            'ASSIGNMENT',
            'CLONE',
            'INCREMENT_DECREMENT',
            'DOM_ELEMENT_CREATE',
            'ARRAY_ACCUMULATE',
            'CONTROL_STATEMENT',
            'METHOD_CALL',
        ], true);
    }

    /**
     * Additional checks to determine if a statement should be reported.
     */
    private function shouldReportStatement(Node $statement): bool
    {
        // Check for loop interrupters (break, continue, return, throw)
        $finder = new NodeFinder();
        $interrupters = $finder->find($statement, static function (Node $node): bool {
            return $node instanceof Break_ ||
                   $node instanceof Continue_ ||
                   $node instanceof Return_;
        });

        // Check if the statement uses variables
        $variables = $finder->find($statement, static function (Node $node): bool {
            return $node instanceof Variable;
        });

        return count($interrupters) === 0 && count($variables) > 0;
    }
}