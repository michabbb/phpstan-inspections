<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\ByReference;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects incorrect passing of arguments to by-reference parameters.
 *
 * This rule identifies cases where:
 * - Function calls are passed to parameters that expect references, but the called function doesn't return by reference
 * - New expressions are passed to by-reference parameters
 * - Non-variable expressions are passed to by-reference parameters (except new expressions in PHP < 7.0)
 *
 * These patterns can cause PHP notices about "only variable references should be returned/passed by reference".
 *
 * @implements Rule<Node>
 */
class PassingByReferenceCorrectnessRule implements Rule
{
    private const SKIPPED_FUNCTIONS = ['current', 'key'];

    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall && !$node instanceof MethodCall) {
            return [];
        }

        $errors = [];

        // Get the function/method reflection
        $callableReflection = $this->getCallableReflection($node, $scope);
        if ($callableReflection === null) {
            return [];
        }

        $parametersAcceptor = $callableReflection->getVariants()[0] ?? null;
        if ($parametersAcceptor === null) {
            return [];
        }
        
        $parameters = $parametersAcceptor->getParameters();
        $arguments = $node instanceof FuncCall ? $node->getArgs() : $node->getArgs();

        // Check each parameter-argument pair
        foreach ($parameters as $index => $parameter) {
            if (!isset($arguments[$index])) {
                continue;
            }

            $argument = $arguments[$index]->value;

            if ($parameter->passedByReference()->yes()) {
                $error = $this->checkArgumentForReferenceParameter($argument, $scope);
                if ($error !== null) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    private function getCallableReflection(Node $node, Scope $scope): FunctionReflection|MethodReflection|null
    {
        if ($node instanceof FuncCall) {
            $functionName = $node->name;
            if (!$functionName instanceof Node\Name) {
                return null;
            }

            $functionNameString = $functionName->toString();

            // Skip certain built-in functions
            if (in_array($functionNameString, self::SKIPPED_FUNCTIONS, true)) {
                return null;
            }

            if (!$this->reflectionProvider->hasFunction($functionName, $scope)) {
                return null;
            }

            return $this->reflectionProvider->getFunction($functionName, $scope);
        }

        if ($node instanceof MethodCall) {
            $methodName = $node->name;
            if (!$methodName instanceof Node\Identifier) {
                return null;
            }

            $callerType = $scope->getType($node->var);
            $methodReflection = $scope->getMethodReflection($callerType, $methodName->toString());

            return $methodReflection;
        }

        return null;
    }

    private function checkArgumentForReferenceParameter(Node $argument, Scope $scope): ?\PHPStan\Rules\RuleError
    {
        // Variables are always acceptable for by-reference parameters
        if ($argument instanceof Variable) {
            return null;
        }

        // New expressions are acceptable in PHP < 7.0, but let's be strict and flag them
        if ($argument instanceof New_) {
            return RuleErrorBuilder::message('Only variable references should be passed by reference, not new expressions')
                ->identifier('function.byReferenceCall')
                ->line($argument->getStartLine())
                ->build();
        }

        // Function calls need to check if they return by reference
        if ($argument instanceof FuncCall) {
            $functionReflection = $this->getCallableReflection($argument, $scope);
            if ($functionReflection instanceof FunctionReflection) {
                // If the function returns by reference, it's acceptable
                if ($functionReflection->returnsByReference()->yes()) {
                    return null;
                }
                return RuleErrorBuilder::message('Only variable references should be passed by reference, not function call results')
                    ->identifier('function.byReferenceCall')
                    ->line($argument->getStartLine())
                    ->build();
            }
        }

        // Method calls need to check if they return by reference
        if ($argument instanceof MethodCall) {
            $methodReflection = $this->getCallableReflection($argument, $scope);
            if ($methodReflection instanceof MethodReflection) {
                // If the method returns by reference, it's acceptable
                if ($methodReflection->returnsByReference()->yes()) {
                    return null;
                }
                return RuleErrorBuilder::message('Only variable references should be passed by reference, not method call results')
                    ->identifier('function.byReferenceCall')
                    ->line($argument->getStartLine())
                    ->build();
            }
        }

        // Any other expression type is not acceptable for by-reference parameters
        return RuleErrorBuilder::message('Only variable references should be passed by reference')
            ->identifier('function.byReferenceCall')
            ->line($argument->getStartLine())
            ->build();
    }
}