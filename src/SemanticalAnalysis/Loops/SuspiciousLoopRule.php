<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Loops;

use PhpParser\Node;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Closure;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects suspicious loop patterns that may indicate bugs or poor code quality.
 *
 * This rule identifies:
 * - For loops with multiple conditions that should use && or || operators
 * - Loop variables that override function or method parameters
 * - Loop variables that override variables from outer loops
 *
 * @implements Rule<FunctionLike>
 */
class SuspiciousLoopRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FunctionLike) {
            return [];
        }

        $errors = [];
        
        // Get all statements from the function/method
        $stmts = [];
        if ($node instanceof ClassMethod || $node instanceof Function_) {
            $stmts = $node->stmts ?? [];
        } elseif ($node instanceof Closure) {
            $stmts = $node->stmts;
        }

        // Find all loops in the function
        $nodeFinder = new NodeFinder();
        $loops = $nodeFinder->find($stmts, static function (Node $n) {
            return $n instanceof For_ || $n instanceof Foreach_;
        });

        // Process each loop
        foreach ($loops as $loop) {
            if ($loop instanceof For_) {
                $errors = array_merge($errors, $this->checkMultipleConditions($loop));
            }
            
            $errors = array_merge($errors, $this->checkParameterOverrides($loop, $node));
            $errors = array_merge($errors, $this->checkOuterLoopOverrides($loop, $loops));
        }

        return $errors;
    }

    /**
     * Check for loops with multiple conditions that should use && or ||
     */
    private function checkMultipleConditions(For_ $node): array
    {
        if (count($node->cond) > 1) {
            return [
                RuleErrorBuilder::message('Please use && or || for multiple conditions. Currently no checks are performed after first positive result.')
                    ->identifier('loop.multipleConditions')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Check if loop variables override function/method parameters
     */
    private function checkParameterOverrides(Node $loop, FunctionLike $function): array
    {
        $errors = [];
        $loopVariables = $this->getLoopVariables($loop);
        
        // Get parameter names
        $parameters = [];
        foreach ($function->getParams() as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $parameters[] = $param->var->name;
            }
        }

        // Check for conflicts
        foreach ($loopVariables as $loopVar) {
            if (in_array($loopVar, $parameters, true)) {
                $type = $function instanceof ClassMethod ? 'method' : 'function';
                $errors[] = RuleErrorBuilder::message(
                    sprintf('Variable \'%s\' is introduced as a %s parameter and overridden here.', $loopVar, $type)
                )
                    ->identifier('loop.parameterOverride')
                    ->line($loop->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * Check if loop variables override variables from outer loops
     */
    private function checkOuterLoopOverrides(Node $currentLoop, array $allLoops): array
    {
        $errors = [];
        $currentLoopVariables = $this->getLoopVariables($currentLoop);
        
        // Find potential outer loops (loops that start before the current one)
        foreach ($allLoops as $otherLoop) {
            if ($otherLoop === $currentLoop) {
                continue;
            }
            
            // Simple heuristic: if the other loop starts before the current one,
            // it could be an outer loop
            if ($otherLoop->getStartLine() < $currentLoop->getStartLine() && 
                $otherLoop->getEndLine() > $currentLoop->getEndLine()) {
                
                $outerLoopVariables = $this->getLoopVariables($otherLoop);
                
                foreach ($currentLoopVariables as $currentVar) {
                    if (in_array($currentVar, $outerLoopVariables, true)) {
                        $errors[] = RuleErrorBuilder::message(
                            sprintf('Variable \'%s\' is introduced in a outer loop and overridden here.', $currentVar)
                        )
                            ->identifier('loop.outerVariableOverride')
                            ->line($currentLoop->getStartLine())
                            ->build();
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Extract loop variables from for/foreach statements
     */
    private function getLoopVariables(Node $node): array
    {
        $variables = [];

        if ($node instanceof For_) {
            // Get variables from initialization expressions
            foreach ($node->init as $init) {
                if ($init instanceof Node\Expr\Assign) {
                    $var = $init->var;
                    if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
                        $variables[] = $var->name;
                    }
                }
            }
        } elseif ($node instanceof Foreach_) {
            // Get variables from foreach value and key
            if ($node->valueVar instanceof Node\Expr\Variable && is_string($node->valueVar->name)) {
                $variables[] = $node->valueVar->name;
            }
            if ($node->keyVar instanceof Node\Expr\Variable && is_string($node->keyVar->name)) {
                $variables[] = $node->keyVar->name;
            }
        }

        return $variables;
    }
}