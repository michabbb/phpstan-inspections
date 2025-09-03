<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects static method invocations using '->' instead of '::'.
 *
 * This rule identifies cases where static methods are called using the object operator (->)
 * instead of the scope resolution operator (::), which can be confusing and may indicate
 * refactoring artifacts that don't reflect the actual architecture.
 *
 * The rule detects:
 * - $this->staticMethod() → should use self::staticMethod()
 * - $expression->staticMethod() → should use ClassName::staticMethod()
 *
 * Exceptions are made for PHPUnit assertions and Eloquent model methods.
 *
 * @implements Rule<MethodCall>
 */
final class StaticInvocationViaThisRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        // Get the method name
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        // Get the type of the variable first
        $varType = $scope->getType($node->var);
        
        // Try to get method reflection from the variable type
        $methodReflection = $scope->getMethodReflection($varType, $methodName);
        if (!$methodReflection instanceof MethodReflection) {
            return [];
        }

        // Only process static methods
        if (!$methodReflection->isStatic()) {
            return [];
        }

        // Skip PHPUnit assertions
        if ($this->shouldSkipPHPUnit($methodReflection)) {
            return [];
        }

        // Skip Eloquent models
        if ($this->shouldSkipEloquent($methodReflection)) {
            return [];
        }

        // Check if it's $this->staticMethod()
        if ($node->var instanceof Variable && $node->var->name === 'this') {
            return [
                RuleErrorBuilder::message(
                    sprintf("'self::%s(...)' should be used instead.", $methodName)
                )
                    ->identifier('static.invocationViaThis')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        // Check if it's $expression->staticMethod() (not a parameter or use variable)
        if (!$this->isParameterOrUseVariable($node->var, $scope)) {
            return [
                RuleErrorBuilder::message(
                    sprintf("'...::%s(...)' should be used instead.", $methodName)
                )
                    ->identifier('static.invocationViaThis')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function shouldSkipPHPUnit(MethodReflection $methodReflection): bool
    {
        $declaringClass = $methodReflection->getDeclaringClass();
        $className = $declaringClass->getName();

        // Check for PHPUnit namespace
        if (str_starts_with($className, 'PHPUnit\\')) {
            return true;
        }

        // Also check for PHPUnit_Framework (older versions)
        if (str_starts_with($className, 'PHPUnit_Framework\\')) {
            return true;
        }

        return false;
    }

    private function shouldSkipEloquent(MethodReflection $methodReflection): bool
    {
        $declaringClass = $methodReflection->getDeclaringClass();
        $className = $declaringClass->getName();

        // Check for Eloquent Model methods
        if (str_starts_with($className, 'Illuminate\\Database\\Eloquent\\Model')) {
            return true;
        }

        // Check if the class extends Eloquent Model
        $parentClasses = $declaringClass->getParentClassesNames();
        foreach ($parentClasses as $parentClass) {
            if (str_starts_with($parentClass, 'Illuminate\\Database\\Eloquent\\Model')) {
                return true;
            }
        }

        // Also check for common Eloquent builder methods that are often called statically
        $commonEloquentMethods = [
            'where', 'whereNull', 'whereNotNull', 'orWhere', 'find', 'findOrFail', 
            'first', 'get', 'count', 'exists', 'orderBy', 'groupBy', 'having',
            'select', 'join', 'leftJoin', 'rightJoin', 'limit', 'offset', 'take',
            'skip', 'create', 'insert', 'update', 'delete', 'truncate', 'value'
        ];
        
        if (in_array($methodReflection->getName(), $commonEloquentMethods, true)) {
            return true;
        }

        return false;
    }

    private function isParameterOrUseVariable(Node $var, Scope $scope): bool
    {
        // Only check variables
        if (!$var instanceof Variable) {
            return false;
        }

        $varName = $var->name;
        if (!is_string($varName)) {
            return false;
        }

        // Get the current function/method scope
        $function = $scope->getFunction();
        if ($function === null) {
            return false;
        }

        // Check if it's a parameter
        foreach ($function->getParameters() as $parameter) {
            if ($parameter->getName() === $varName) {
                return true;
            }
        }

        // For use variables, we can't easily check them with current PHPStan API
        // so we'll simplified and skip this check for now
        return false;
    }
}