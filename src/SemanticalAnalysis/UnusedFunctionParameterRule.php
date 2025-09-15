<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unused parameters in functions and methods.
 *
 * This rule identifies function/method parameters that are defined but never used within
 * the function/method body. Unused parameters can indicate dead code or potential bugs.
 * Parameters starting with underscore (_) are ignored as they are commonly used
 * for intentionally unused parameters.
 *
 * Edge cases handled:
 * - Ignores parameters with underscore prefix (_param)
 * - Ignores parameters passed by reference (&$param) - may have side effects
 * - Ignores constructor parameters (may be used for property initialization)
 * - Ignores magic methods (__call, __invoke, etc.)
 * - Ignores abstract methods (no implementation to analyze)
 * - Ignores interface methods (no implementation to analyze)
 *
 * @implements Rule<Node\FunctionLike>
 */
class UnusedFunctionParameterRule implements Rule
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

        // Skip closures - they're handled by UnusedClosureParameterRule
        if ($node instanceof Node\Expr\Closure) {
            return [];
        }

        // Skip if no parameters
        $params = $node->getParams();
        if (empty($params)) {
            return [];
        }

        // Skip abstract methods (no implementation to analyze)
        if ($node instanceof Node\Stmt\ClassMethod && $node->isAbstract()) {
            return [];
        }

        // Skip if no statements (interface methods, abstract methods)
        $stmts = $node->getStmts();
        if ($stmts === null || empty($stmts)) {
            return [];
        }

        // Skip constructors - parameters might be used for property initialization
        if ($node instanceof Node\Stmt\ClassMethod && $node->name->toLowerString() === '__construct') {
            return [];
        }

        // Skip magic methods - parameters may be used in non-obvious ways
        if ($node instanceof Node\Stmt\ClassMethod && str_starts_with($node->name->toLowerString(), '__')) {
            return [];
        }

        // Find all used variable names in the function/method body
        $usedVariableNames = $this->findUsedVariables($stmts);

        $errors = [];
        foreach ($params as $param) {
            if ($param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $paramName = $param->var->name;

                // Ignore variables starting with _ (intentionally unused)
                if (str_starts_with($paramName, '_')) {
                    continue;
                }

                // Ignore parameters passed by reference (may have side effects)
                if ($param->byRef) {
                    continue;
                }

                // Check if parameter is used in the function body
                if (!isset($usedVariableNames[$paramName])) {
                    $functionName = $this->getFunctionName($node, $scope);
                    $errors[] = RuleErrorBuilder::message(
                        "Parameter \$$paramName is unused in $functionName."
                    )
                        ->identifier('parameter.unused')
                        ->line($param->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * Find all variable names used in the given statements.
     *
     * @param Node\Stmt[] $stmts
     * @return array<string, true>
     */
    private function findUsedVariables(array $stmts): array
    {
        $traverser = new NodeTraverser();
        $visitor = new class extends NodeVisitorAbstract {
            /** @var array<string, true> */
            public array $usedVariableNames = [];

            public function enterNode(Node $node) {
                // Track variable usage (reading)
                if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
                    $this->usedVariableNames[$node->name] = true;
                }

                // Track variable usage in assignments (writing)
                if ($node instanceof Node\Expr\Assign &&
                    $node->var instanceof Node\Expr\Variable &&
                    is_string($node->var->name)) {
                    $this->usedVariableNames[$node->var->name] = true;
                }

                // Track variable usage in compound assignments (+=, -=, etc.)
                if ($node instanceof Node\Expr\AssignOp &&
                    $node->var instanceof Node\Expr\Variable &&
                    is_string($node->var->name)) {
                    $this->usedVariableNames[$node->var->name] = true;
                }

                // Track variable usage in compact() function calls
                if ($node instanceof Node\Expr\FuncCall &&
                    $node->name instanceof Node\Name &&
                    strtolower($node->name->toString()) === 'compact') {
                    $this->processCompactCall($node);
                }

                return null;
            }

            /**
             * Process compact() function call arguments to extract used variable names.
             */
            private function processCompactCall(Node\Expr\FuncCall $funcCall): void
            {
                foreach ($funcCall->getArgs() as $arg) {
                    $this->extractVariableNamesFromCompactArg($arg->value);
                }
            }

            /**
             * Extract variable names from compact() arguments recursively.
             */
            private function extractVariableNamesFromCompactArg(Node $node): void
            {
                if ($node instanceof Node\Scalar\String_) {
                    // String literal argument: compact('varname')
                    $this->usedVariableNames[$node->value] = true;
                } elseif ($node instanceof Node\Expr\Array_) {
                    // Array argument: compact(['var1', 'var2'])
                    foreach ($node->items as $item) {
                        if ($item !== null && $item->value !== null) {
                            $this->extractVariableNamesFromCompactArg($item->value);
                        }
                    }
                }
                // Note: We don't handle dynamic variable names (e.g., compact($varName))
                // as they cannot be statically analyzed reliably
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->usedVariableNames;
    }

    /**
     * Get a descriptive name for the function/method for error messages.
     */
    private function getFunctionName(Node\FunctionLike $node, Scope $scope): string
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $className = $scope->isInClass() ? $scope->getClassReflection()->getName() : 'unknown';
            return $className . '::' . $node->name->toString() . '()';
        }

        if ($node instanceof Node\Stmt\Function_) {
            return $node->name !== null ? $node->name->toString() . '()' : 'anonymous function';
        }

        if ($node instanceof Node\Expr\Closure) {
            return 'closure';
        }

        return 'function';
    }
}