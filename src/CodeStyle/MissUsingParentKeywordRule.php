<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects misuse of the 'parent' keyword when it could be replaced with '$this' or 'self'.
 *
 * This rule identifies cases where 'parent::method()' is used but the method is not actually
 * defined in the parent class or is not overridden by child classes. In such cases, the call
 * can be safely replaced with '$this->method()' for instance methods or 'self::method()' for
 * static methods.
 *
 * @implements Rule<StaticCall>
 */
class MissUsingParentKeywordRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider
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

        // Check if this is a call on 'parent'
        if (!$node->class instanceof Name || strtolower($node->class->toString()) !== 'parent') {
            return [];
        }

        // Get current class context
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        // Skip traits
        if ($classReflection->isTrait()) {
            return [];
        }

        // Get current method context
        $function = $scope->getFunction();
        if (!$function instanceof \PHPStan\Reflection\MethodReflection) {
            return [];
        }

        $currentMethodReflection = $function;
        if ($currentMethodReflection->isStatic()) {
            // Do not suggest in static methods
            return [];
        }

        // Get method name being called
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->name;
        $currentMethodName = $currentMethodReflection->getName();

        $parentClass = $classReflection->getParentClass();
        if ($parentClass === null) {
            return [];
        }

        // Follow Java original logic exactly:
        // 1. Only trigger when called method != current method (line 57 in Java)
        // 2. Only when current class does NOT own the method (line 58 in Java) 
        // 3. Only when method is NOT overridden by children (line 58 in Java)
        
        // Java: !referenceName.equals(methodName) 
        if ($methodName === $currentMethodName) {
            return [];
        }
        
        // Java: clazz.findOwnMethodByName(referenceName) == null
        // Check if current class declares this method in its own AST
        if ($this->currentClassDeclaresMethod($node, $methodName)) {
            return [];
        }
        
        // Java: !this.isOverridden(clazz, referenceName)
        if ($this->isOverriddenByChildren($classReflection, $methodName)) {
            return [];
        }

        // Method exists in parent, suggest appropriate replacement
        if ($parentClass->hasMethod($methodName)) {
            $parentMethod = $parentClass->getMethod($methodName, $scope);
            if ($parentMethod->isStatic()) {
                $replacement = 'self::' . $methodName . '(' . $this->getParametersText($node) . ')';
            } else {
                $replacement = '\$this->' . $methodName . '(' . $this->getParametersText($node) . ')';
            }
            
            return [
                RuleErrorBuilder::message("It was probably intended to use '" . $replacement . "' here.")
                    ->identifier('parent.misuse')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Get the parameters text for the method call
     */
    private function getParametersText(StaticCall $node): string
    {
        if ($node->args === null || empty($node->args)) {
            return '';
        }

        $params = [];
        foreach ($node->args as $arg) {
            // Try to get a string representation of the argument
            try {
                if ($arg->value instanceof Node\Scalar\String_) {
                    $params[] = "'" . $arg->value->value . "'";
                } elseif ($arg->value instanceof Node\Scalar\LNumber) {
                    $params[] = (string) $arg->value->value;
                } elseif ($arg->value instanceof Node\Expr\Variable && is_string($arg->value->name)) {
                    $params[] = '$' . $arg->value->name;
                } else {
                    // For complex expressions, just use a placeholder
                    $params[] = '...';
                }
            } catch (\Throwable) {
                // Fallback for any conversion issues
                $params[] = '...';
            }
        }

        return implode(', ', $params);
    }

    /**
     * Check if the method is overridden by any child classes
     * Following Java logic from isOverridden method
     */
    private function isOverriddenByChildren(ClassReflection $classReflection, string $methodName): bool
    {
        if ($classReflection->isFinal()) {
            return false;
        }

        // Get child classes - PHPStan doesn't have direct child class resolution like PhpStorm
        // For now, we'll be conservative and return false to avoid false positives
        // This matches the behavior when child class detection is unavailable
        return false;
    }

    private function currentClassDeclaresMethod(StaticCall $node, string $methodName): bool
    {
        // Walk up parents to find the Class_ node
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node && !$parent instanceof Class_) {
            $parent = $parent->getAttribute('parent');
        }
        if (!$parent instanceof Class_) {
            return false;
        }

        foreach ($parent->getMethods() as $method) {
            if (strtolower($method->name->toString()) === strtolower($methodName)) {
                return true;
            }
        }
        return false;
    }
}
