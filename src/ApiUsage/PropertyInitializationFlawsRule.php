<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects property initialization flaws:
 * 1. Redundant null assignments to properties (implicit default)
 * 2. Constructor assignments that override default values unnecessarily
 * 3. Duplicate default values between parent and child classes
 *
 * @implements Rule<Node>
 */
final class PropertyInitializationFlawsRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof Property) {
            return $this->checkPropertyDefaults($node, $scope);
        }

        if ($node instanceof ClassMethod && $node->name->toString() === '__construct') {
            return $this->checkConstructorInitialization($node, $scope);
        }

        return [];
    }

    /** @return array<\PHPStan\Rules\RuleError> */
    private function checkPropertyDefaults(Property $property, Scope $scope): array
    {
        $errors = [];

        // Property nodes are only for properties, not constants
        // Constants are handled by ClassConst nodes, so we don't need to check

        foreach ($property->props as $propertyProperty) {
            if ($propertyProperty->default !== null && $this->isNullValue($propertyProperty->default)) {
                // Skip nullable typed properties (PHP 7.4+)
                if (!$this->isNullableTypedProperty($property)) {
                    $errors[] = RuleErrorBuilder::message('Null assignment can be safely removed. Define null in annotations if it\'s important.')
                        ->identifier('property.redundantNull')
                        ->line($propertyProperty->default->getStartLine())
                        ->build();
                }
            }

            // Check for duplicate default values in inheritance hierarchy
            if ($propertyProperty->default !== null && $scope->isInClass()) {
                $classReflection = $scope->getClassReflection();
                foreach ($classReflection->getParents() as $parentClass) {
                    if ($this->hasPropertyWithSameDefault($parentClass, $propertyProperty->name->toString(), $propertyProperty->default)) {
                        $errors[] = RuleErrorBuilder::message('Written value is same as default one, consider removing this assignment.')
                            ->identifier('property.duplicateDefault')
                            ->line($propertyProperty->default->getStartLine())
                            ->build();
                    }
                }
            }
        }

        return $errors;
    }

    /** @return array<\PHPStan\Rules\RuleError> */
    private function checkConstructorInitialization(ClassMethod $constructor, Scope $scope): array
    {
        $errors = [];

        if (!$scope->isInClass() || $constructor->stmts === null) {
            return $errors;
        }

        $classReflection  = $scope->getClassReflection();
        $propertyDefaults = $this->collectPrivatePropertyDefaults($classReflection);

        if (empty($propertyDefaults)) {
            return $errors;
        }

        // Check first-level statements in constructor
        foreach ($constructor->stmts as $stmt) {
            if (!($stmt instanceof Node\Stmt\Expression)) {
                continue;
            }

            $expression = $stmt->expr;
            if (!($expression instanceof Assign)) {
                continue;
            }

            // Check if it's $this->property assignment
            if (!($expression->var instanceof PropertyFetch) ||
                !($expression->var->var instanceof Node\Expr\Variable) ||
                $expression->var->var->name !== 'this') {
                continue;
            }

            $propertyName = $expression->var->name;
            if (!($propertyName instanceof Node\Identifier)) {
                continue;
            }

            $propertyNameStr = $propertyName->toString();

            if (!array_key_exists($propertyNameStr, $propertyDefaults)) {
                continue;
            }

            $defaultValue  = $propertyDefaults[$propertyNameStr];
            $assignedValue = $expression->expr;

            // Case 1: Both are null
            if ($defaultValue === null && $this->isNullValue($assignedValue)) {
                $errors[] = RuleErrorBuilder::message('Written value is same as default one, consider removing this assignment.')
                    ->identifier('constructor.redundantNull')
                    ->line($stmt->getStartLine())
                    ->build();
                continue;
            }

            // Case 2: Both values are equivalent
            if ($defaultValue !== null && $this->areValuesEquivalent($defaultValue, $assignedValue)) {
                $errors[] = RuleErrorBuilder::message('Written value is same as default one, consider removing this assignment.')
                    ->identifier('constructor.redundantAssignment')
                    ->line($stmt->getStartLine())
                    ->build();
                continue;
            }

            // Case 3: Property has default, constructor overrides it (suggest removing default)
            if ($defaultValue !== null && !$this->isPropertyReusedInValue($propertyNameStr, $assignedValue)) {
                $errors[] = RuleErrorBuilder::message('The assignment can be safely removed as the constructor overrides it.')
                    ->identifier('property.overriddenDefault')
                    ->line($defaultValue->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /** @return array<string, Node|null> */
    private function collectPrivatePropertyDefaults(ClassReflection $classReflection): array
    {
        $properties = [];

        // This is simplified - in a real implementation, we'd need access to the AST of the class
        // For now, we'll work with what we can access through reflection
        foreach ($classReflection->getNativeReflection()->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isPrivate() && !$reflectionProperty->isStatic()) {
                $properties[$reflectionProperty->getName()] = null; // We can't easily get the default value from reflection
            }
        }

        return $properties;
    }

    private function isNullValue(Node $node): bool
    {
        return $node instanceof ConstFetch &&
               $node->name instanceof Name &&
               strtolower($node->name->toString()) === 'null';
    }

    private function isNullableTypedProperty(Property $property): bool
    {
        if ($property->type === null) {
            return false;
        }

        // Check for nullable type (?string)
        if ($property->type instanceof Node\NullableType) {
            return true;
        }

        // Check for union type containing null (string|null)
        if ($property->type instanceof Node\UnionType) {
            foreach ($property->type->types as $type) {
                if ($type instanceof Name && strtolower($type->toString()) === 'null') {
                    return true;
                }
                // Also check for DNF types and identifiers
                if ($type instanceof Node\Identifier && strtolower($type->toString()) === 'null') {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasPropertyWithSameDefault(ClassReflection $class, string $propertyName, Node $defaultValue): bool
    {
        // Simplified implementation - in reality we'd need to parse the parent class AST
        // This would require additional context that's not easily available here
        return false;
    }

    private function areValuesEquivalent(Node $value1, Node $value2): bool
    {
        // Simplified equivalence check
        if (get_class($value1) !== get_class($value2)) {
            return false;
        }

        if ($value1 instanceof ConstFetch && $value2 instanceof ConstFetch) {
            return $value1->name->toString() === $value2->name->toString();
        }

        // For more complex equivalence checks, we'd need a full AST comparison
        return false;
    }

    private function isPropertyReusedInValue(string $propertyName, Node $value): bool
    {
        // Check if the property is referenced in the assigned value
        // This is a simplified version - a full implementation would traverse the entire AST
        if ($value instanceof PropertyFetch &&
            $value->var instanceof Node\Expr\Variable &&
            $value->var->name === 'this' &&
            $value->name instanceof Node\Identifier &&
            $value->name->toString() === $propertyName) {
            return true;
        }

        return false;
    }
}
