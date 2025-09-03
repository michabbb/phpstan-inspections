<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Laravel-optimized version of StaticInvocationViaThisRule that properly handles
 * Eloquent magic methods and Laravel-specific patterns using dynamic reflection.
 *
 * This rule identifies cases where static methods are called using the object operator (->)
 * instead of the scope resolution operator (::), with enhanced Laravel/Eloquent support.
 *
 * Uses dynamic reflection to detect Laravel/Eloquent methods instead of hardcoded lists.
 *
 * @implements Rule<MethodCall>
 */
final class StaticInvocationViaThisLaravelRule implements Rule
{
    /** @var array<string, array<string>> Cache for reflected methods */
    private static array $methodCache = [];

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

        // Skip Laravel/Eloquent methods (using dynamic reflection)
        if ($this->shouldSkipLaravelEloquent($methodReflection, $methodName)) {
            return [];
        }

        // Check if it's $this->staticMethod()
        if ($node->var instanceof Variable && $node->var->name === 'this') {
            return [
                RuleErrorBuilder::message(
                    sprintf("'self::%s(...)' should be used instead.", $methodName)
                )
                    ->identifier('static.invocationViaThis.laravel')
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
                    ->identifier('static.invocationViaThis.laravel')
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

        return str_starts_with($className, 'PHPUnit\\') || 
               str_starts_with($className, 'PHPUnit_Framework\\');
    }

    /**
     * Enhanced Laravel/Eloquent method detection using dynamic reflection
     */
    private function shouldSkipLaravelEloquent(MethodReflection $methodReflection, string $methodName): bool
    {
        $declaringClass = $methodReflection->getDeclaringClass();
        $className = $declaringClass->getName();

        // Laravel Facade methods - always skip for Facades
        if (str_contains($className, 'Illuminate\\Support\\Facades\\') || 
            str_ends_with($className, 'Facade')) {
            return true;
        }

        // Check if this method exists in core Laravel/Eloquent classes
        if ($this->isLaravelCoreMethod($methodName)) {
            return true;
        }

        // Check if the declaring class is or extends Laravel core classes
        if ($this->isLaravelCoreClass($declaringClass)) {
            return true;
        }

        // Eloquent magic methods - skip all where* methods (whereColumn, whereNotColumn, etc.)
        if (str_starts_with($methodName, 'where')) {
            return true;
        }

        return false;
    }

    /**
     * Check if method exists in core Laravel/Eloquent classes using reflection
     */
    private function isLaravelCoreMethod(string $methodName): bool
    {
        $coreClasses = [
            'Illuminate\\Database\\Eloquent\\Model',
            'Illuminate\\Database\\Eloquent\\Builder',
            'Illuminate\\Database\\Query\\Builder',
            'Illuminate\\Support\\Collection',
        ];

        foreach ($coreClasses as $coreClass) {
            $methods = $this->getClassMethods($coreClass);
            if (in_array($methodName, $methods, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if class is or extends Laravel core classes
     */
    private function isLaravelCoreClass(ClassReflection $classReflection): bool
    {
        $className = $classReflection->getName();

        // Direct Laravel class check
        if (str_starts_with($className, 'Illuminate\\')) {
            return true;
        }

        // Check parent classes
        $parentClasses = $classReflection->getParentClassesNames();
        foreach ($parentClasses as $parentClass) {
            if (str_starts_with($parentClass, 'Illuminate\\Database\\Eloquent\\Model') ||
                str_starts_with($parentClass, 'Illuminate\\Database\\Eloquent\\Builder') ||
                str_starts_with($parentClass, 'Illuminate\\Database\\Query\\Builder')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all methods from a class using reflection with caching
     * @return array<string>
     */
    private function getClassMethods(string $className): array
    {
        if (isset(self::$methodCache[$className])) {
            return self::$methodCache[$className];
        }

        try {
            if (!class_exists($className)) {
                self::$methodCache[$className] = [];
                return [];
            }

            $reflection = new \ReflectionClass($className);
            $methods = [];
            
            foreach ($reflection->getMethods() as $method) {
                $methods[] = $method->getName();
            }

            self::$methodCache[$className] = $methods;
            return $methods;
            
        } catch (\ReflectionException) {
            self::$methodCache[$className] = [];
            return [];
        }
    }

    private function isParameterOrUseVariable(Node $var, Scope $scope): bool
    {
        if (!$var instanceof Variable) {
            return false;
        }

        $varName = $var->name;
        if (!is_string($varName)) {
            return false;
        }

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

        return false;
    }
}