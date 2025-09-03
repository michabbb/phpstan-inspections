<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\Finally_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects useless return statements and confusing assignments in return statements.
 *
 * This rule identifies:
 * - Senseless return statements: `return;` without value at the end of functions/methods
 * - Confusing assignments in return: `return $local = $value;` can be simplified to `return $value;`
 *
 * The rule helps improve code clarity by removing unnecessary constructs and simplifying
 * return statements with assignments when the assigned variable is not used elsewhere.
 *
 * @implements Rule<Node\FunctionLike>
 */
class UselessReturnRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\FunctionLike) {
            return [];
        }

        $errors = [];

        // Check for senseless return at the end of function/method
        $senselessError = $this->checkSenselessReturn($node);
        if ($senselessError !== null) {
            $errors[] = $senselessError;
        }

        // Check all return statements in this function for confusing assignments
        $assignmentErrors = $this->checkAllConfusingAssignments($node);
        $errors = array_merge($errors, $assignmentErrors);

        return $errors;
    }

    private function checkSenselessReturn(Node\FunctionLike $function): ?RuleError
    {
        // Skip abstract methods (following Java original)
        if ($function instanceof ClassMethod && $function->isAbstract()) {
            return null;
        }

        $stmts = $function->getStmts();
        if ($stmts === null || empty($stmts)) {
            return null;
        }

        // Walk backwards to find the last non-NOP statement (following O3's advice)
        for ($i = count($stmts) - 1; $i >= 0; $i--) {
            $candidate = $stmts[$i];
            if ($candidate instanceof Nop) {
                continue; // Skip comment-related NOP statements
            }
            
            // Check if the last executable statement is a bare return;
            if ($candidate instanceof Return_ && $candidate->expr === null) {
                return RuleErrorBuilder::message('Senseless statement: return null implicitly or safely remove it.')
                    ->identifier('return.senseless')
                    ->line($candidate->getStartLine())
                    ->build();
            }
            
            // First non-NOP is not a bare return → nothing to report
            break;
        }

        return null;
    }

    private function checkAllConfusingAssignments(Node\FunctionLike $function): array
    {
        $errors = [];
        $stmts = $function->getStmts();
        
        if ($stmts === null) {
            return $errors;
        }

        // Find all return statements in this function
        $nodeFinder = new NodeFinder();
        $returnStatements = $nodeFinder->findInstanceOf($stmts, Return_::class);

        foreach ($returnStatements as $return) {
            $error = $this->checkConfusingAssignment($return, $function);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }


    private function checkConfusingAssignment(Return_ $return, Node\FunctionLike $function): ?RuleError
    {
        if ($return->expr === null) {
            return null;
        }

        // Check if the return expression is an assignment
        if (!$return->expr instanceof Assign) {
            return null;
        }

        $assignment = $return->expr;
        $variable = $assignment->var;
        $value = $assignment->expr;

        // Only check variable assignments
        if (!$variable instanceof Variable) {
            return null;
        }

        $variableName = $variable->name;
        if (!is_string($variableName)) {
            return null;
        }

        // Now we can properly check if this variable should be targeted for simplification
        if (!$this->isVariableTarget($variableName, $function)) {
            return null;
        }

        $replacement = 'return ' . $this->getExpressionText($value) . ';';

        return RuleErrorBuilder::message('Assignment here is not making much sense. Replace with: ' . $replacement)
            ->identifier('return.confusingAssignment')
            ->line($return->getStartLine())
            ->build();
    }


    private function isVariableTarget(string $variableName, Node\FunctionLike $function): bool
    {
        // Check if variable is a parameter passed by reference
        if ($this->isArgumentReference($variableName, $function)) {
            return false;
        }

        // Check if variable is bound in closure
        if ($this->isBoundReference($variableName, $function)) {
            return false;
        }

        // Check if variable is static
        if ($this->isStaticVariable($variableName, $function)) {
            return false;
        }

        // Check if variable is used in finally block
        if ($this->isUsedInFinally($variableName, $function)) {
            return false;
        }

        return true;
    }

    private function isArgumentReference(string $variableName, Node\FunctionLike $function): bool
    {
        foreach ($function->params as $param) {
            if ($param->var instanceof Variable && $param->var->name === $variableName && $param->byRef) {
                return true;
            }
        }
        return false;
    }

    private function isBoundReference(string $variableName, Node\FunctionLike $function): bool
    {
        // This is a simplified check - in practice, we'd need to analyze closures
        // For now, we'll be conservative and return false
        return false;
    }

    private function isStaticVariable(string $variableName, Node\FunctionLike $function): bool
    {
        if ($function->getStmts() === null) {
            return false;
        }

        $nodeFinder = new NodeFinder();
        $staticStmts = $nodeFinder->findInstanceOf($function->getStmts(), Node\Stmt\Static_::class);

        foreach ($staticStmts as $staticStmt) {
            foreach ($staticStmt->vars as $staticVar) {
                if ($staticVar->var instanceof Variable && $staticVar->var->name === $variableName) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isUsedInFinally(string $variableName, Node\FunctionLike $function): bool
    {
        if ($function->getStmts() === null) {
            return false;
        }

        $nodeFinder = new NodeFinder();
        $tryCatchStmts = $nodeFinder->findInstanceOf($function->getStmts(), TryCatch::class);

        foreach ($tryCatchStmts as $tryCatch) {
            if ($tryCatch->finally !== null) {
                $finallyStmts = $tryCatch->finally->stmts;
                $variables = $nodeFinder->findInstanceOf($finallyStmts, Variable::class);

                foreach ($variables as $variable) {
                    if ($variable->name === $variableName) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getExpressionText(Node $node): string
    {
        // This is a simplified implementation
        // In a real-world scenario, you'd want to use a proper AST printer
        if ($node instanceof Variable) {
            return '$' . $node->name;
        }

        if ($node instanceof Node\Scalar\String_) {
            return '\'' . addslashes($node->value) . '\'';
        }

        if ($node instanceof Node\Scalar\LNumber) {
            return (string) $node->value;
        }

        // For other expressions, return a placeholder
        return '...';
    }
}