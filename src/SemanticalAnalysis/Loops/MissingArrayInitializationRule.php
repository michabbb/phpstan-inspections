<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Loops;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Implements MissingArrayInitializationInspector from Php Inspections (EA Extended).
 *
 * This rule detects missing array initialization in nested loops when arrays are accessed
 * with empty brackets (e.g., $array[]). It analyzes nested loops and reports suspicious
 * array pushes that may indicate missing array initialization.
 *
 * The rule identifies:
 * - Array access expressions with empty brackets ($array[]) in deeply nested loops (>= 2 levels)
 * - Variables that are not initialized as arrays before use
 * - Excludes function parameters and use-variables from consideration
 * - Excludes cases where arrays are properly assigned or initialized in foreach loops
 *
 * Such patterns often indicate that the array should be initialized before the loop
 * to avoid undefined variable notices or unexpected behavior.
 *
 * @implements Rule<Node>
 */
final class MissingArrayInitializationRule implements Rule
{
    public const string IDENTIFIER_MISSING_ARRAY_INIT = 'array.initialization.missing';

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        // We process entire function bodies at once to build context.
        return Node::class;
    }

    /**
     * @param Node $node
     * @return array<int, \PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // We only want to analyze the bodies of functions, methods, and closures.
        if (!$node instanceof ClassMethod && !$node instanceof Function_ && !$node instanceof Closure) {
            return [];
        }

        $functionNode = $node;

        // This visitor traverses the function's AST to find array appends inside nested loops.
        $visitor = new class extends NodeVisitorAbstract {
            /** @var ArrayDimFetch[] */
            public array $candidateNodes = [];
            private int $loopLevel = 0;

            public function enterNode(Node $node)
            {
                if ($this->isLoopStatement($node)) {
                    $this->loopLevel++;
                }

                // A candidate is an array append `[]` inside at least two nested loops.
                if ($this->loopLevel >= 2 && $node instanceof ArrayDimFetch && $node->dim === null) {
                    $this->candidateNodes[] = $node;
                }

                return null; // Continue traversal
            }

            public function leaveNode(Node $node)
            {
                if ($this->isLoopStatement($node)) {
                    $this->loopLevel--;
                }
                return null;
            }

            private function isLoopStatement(Node $node): bool
            {
                return $node instanceof For_ || $node instanceof Foreach_ || $node instanceof While_ || $node instanceof Do_;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        
        // Get the statements from the function node
        $stmts = [];
        if ($functionNode instanceof ClassMethod || $functionNode instanceof Function_) {
            $stmts = $functionNode->stmts ?? [];
        } elseif ($functionNode instanceof Closure) {
            $stmts = $functionNode->stmts;
        }
        
        $traverser->traverse($stmts);

        $errors = [];
        foreach ($visitor->candidateNodes as $candidateNode) {
            $arrayVarName = $this->getBaseArrayVariableName($candidateNode);
            if ($arrayVarName === null) {
                continue;
            }

            // --- Run exclusion checks ---

            // 1. Exclude function parameters.
            if ($this->isFunctionParameter($arrayVarName, $functionNode)) {
                continue;
            }

            // 2. Exclude closure `use` variables.
            if ($functionNode instanceof Closure && $this->isUseVariable($arrayVarName, $functionNode)) {
                continue;
            }

            // 3. Exclude variables that are iterated over in a foreach loop.
            if ($this->isUsedInForeach($arrayVarName, $functionNode)) {
                continue;
            }

            // 4. Exclude variables that were initialized before this node.
            if ($this->isArrayInitialized($arrayVarName, $candidateNode, $functionNode)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(
                sprintf('Array $%s is not initialized before being used in a nested loop.', $arrayVarName)
            )
                ->identifier(self::IDENTIFIER_MISSING_ARRAY_INIT)
                ->line($candidateNode->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Get the base array variable name from an array access expression.
     */
    private function getBaseArrayVariableName(ArrayDimFetch $node): ?string
    {
        $current = $node->var;
        while ($current instanceof ArrayDimFetch) {
            $current = $current->var;
        }

        if ($current instanceof Variable && is_string($current->name)) {
            return $current->name;
        }

        return null;
    }

    /**
     * Check if a variable is a function parameter.
     */
    private function isFunctionParameter(string $varName, Node $functionScope): bool
    {
        if (!$functionScope instanceof Function_ && !$functionScope instanceof ClassMethod && !$functionScope instanceof Closure) {
            return false;
        }

        foreach ($functionScope->getParams() as $param) {
            if ($param->var instanceof Variable && $param->var->name === $varName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a variable is a use-variable in a closure.
     */
    private function isUseVariable(string $varName, Closure $closure): bool
    {
        foreach ($closure->uses as $use) {
            if ($use->var instanceof Variable && $use->var->name === $varName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if an array variable is initialized before the current node.
     */
    private function isArrayInitialized(string $varName, Node $currentNode, Node $functionScope): bool
    {
        $finder = new NodeFinder();
        $assignments = $finder->find($functionScope, function (Node $node) use ($varName, $currentNode): bool {
            if (!$node instanceof Assign) {
                return false;
            }

            // Check if this assignment occurs before the node we're checking.
            if ($node->getStartLine() >= $currentNode->getStartLine()) {
                return false;
            }

            $target = $node->var;
            if ($target instanceof Variable && is_string($target->name) && $target->name === $varName) {
                return true;
            }

            return false;
        });

        return count($assignments) > 0;
    }

    /**
     * Check if an array variable is used in a foreach loop.
     */
    private function isUsedInForeach(string $varName, Node $functionScope): bool
    {
        $finder = new NodeFinder();
        $foreachStatements = $finder->find($functionScope, function (Node $node) use ($varName): bool {
            if (!$node instanceof Foreach_) {
                return false;
            }

            $expr = $node->expr;
            if ($expr instanceof Variable && is_string($expr->name) && $expr->name === $varName) {
                return true;
            }

            return false;
        });

        return count($foreachStatements) > 0;
    }
}