<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects dynamic method invocations using '::' instead of '->'.
 *
 * This rule identifies cases where instance methods are called using the scope resolution operator (::)
 * instead of the object operator (->). Such calls can be refactoring artifacts that don't reflect
 * the actual architecture state.
 *
 * This rule detects:
 * - Calls like static::method(), self::method(), or ClassName::method() where the method is an instance method
 * - Calls like $obj::method() where the method should be called on the object instance
 *
 * Recommended solutions:
 * - Use $this->method() for instance methods within the same class
 * - Use $object->method() for instance methods on object instances
 *
 * @implements Rule<StaticCall>
 */
class DynamicInvocationViaScopeResolutionRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof StaticCall) {
            return [];
        }

        // Get the method name
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->name;

        // Get the class reference
        if (!$node->class instanceof Name) {
            return [];
        }

        // Try to resolve the class
        $className = $node->class->toString();
        if (!$this->reflectionProvider->hasClass($className)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        // Check if the method exists
        if (!$classReflection->hasMethod($methodName)) {
            return [];
        }

        $methodReflection = $classReflection->getMethod($methodName, $scope);

        // Skip if method is static, abstract, or class is interface
        if ($methodReflection->isStatic() || $methodReflection->isAbstract() || $classReflection->isInterface()) {
            return [];
        }

        // Check if we're in a method context
        $currentFunction = $scope->getFunction();
        if (!$currentFunction instanceof MethodReflection) {
            return [];
        }

        $currentMethod = $currentFunction;
        $currentClass = $currentMethod->getDeclaringClass();

        // Pattern 1: static::, self::, or ClassName:: calls
        if ($this->isSelfStaticOrClassReference($node->class, $classReflection, $currentClass)) {
            // Skip if method name matches current method (recursive call)
            if (strcasecmp($methodName, $currentMethod->getName()) === 0) {
                return [];
            }

            // Check if current class has the method (would make it accessible)
            if ($currentClass->hasMethod($methodName)) {
                if ($currentMethod->isStatic()) {
                    // Static method calling instance method - suggest object call
                    return [
                        RuleErrorBuilder::message(
                            sprintf("'%s->%s(...)' should be used instead.", $className, $methodName)
                        )
                            ->identifier('methodCall.dynamicInvocationViaScopeResolution')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                } else {
                    // Instance method calling instance method - suggest $this
                    return [
                        RuleErrorBuilder::message(
                            sprintf("'\$this->%s(...)' should be used instead.", $methodName)
                        )
                            ->identifier('methodCall.dynamicInvocationViaScopeResolution')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }
        } else {
            // Pattern 2: expression::method calls (not class references)
            if (!$this->isClassReference($node->class)) {
                return [
                    RuleErrorBuilder::message(
                        sprintf("'...->%s(...)' should be used instead.", $methodName)
                    )
                        ->identifier('methodCall.dynamicInvocationViaScopeResolution')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function isSelfStaticOrClassReference(Name $className, ClassReflection $targetClass, ClassReflection $currentClass): bool
    {
        $name = $className->toString();

        return $name === 'static' ||
               $name === 'self' ||
               $name === 'parent' ||
               $name === $targetClass->getName() ||
               $name === $currentClass->getName();
    }

    private function isClassReference(Name $className): bool
    {
        $name = $className->toString();

        // Consider it a class reference if it's not a special keyword and looks like a class name
        return !in_array($name, ['static', 'self', 'parent'], true) &&
               ctype_upper($name[0]); // Class names typically start with uppercase
    }
}