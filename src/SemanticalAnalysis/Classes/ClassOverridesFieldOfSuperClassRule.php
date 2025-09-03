<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;

/**
 * Detects when a class property overrides a field from a parent class.
 *
 * This rule warns when a class defines a property with the same name as a property in a parent class,
 * which can lead to confusion and maintenance issues. It considers access level changes and
 * provides appropriate warnings based on visibility rules.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Property>
 */
final class ClassOverridesFieldOfSuperClassRule implements Rule
{
    public function getNodeType(): string
    {
        return Property::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Property) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || $classReflection->isAnonymous()) {
            return [];
        }

        // Skip static properties and constants
        if ($node->isStatic() || $node->isReadonly()) { // Readonly properties are effectively constants in this context
            return [];
        }

        $propertyName = $node->props[0]->name->toString();
        $errors = [];

        foreach ($classReflection->getParents() as $parentClassReflection) {
            if ($parentClassReflection->hasProperty($propertyName)) {
                $parentProperty = $parentClassReflection->getProperty($propertyName, $scope);

                // Skip if the parent property is static
                if ($parentProperty->isStatic()) {
                    continue;
                }

                // Replicate Java inspector's logic for private redefinition
                if ($parentProperty->isPrivate()) {
                    // Exception: Allow Laravel/Symfony Command pattern where protected $signature/$description 
                    // are expected to be defined even though parent has private properties
                    if ($this->isFrameworkCommandPattern($classReflection, $parentClassReflection, $propertyName)) {
                        continue; // Skip this parent, but check other parents
                    }
                    
                    // The Java inspector has an option REPORT_PRIVATE_REDEFINITION, which is true by default.
                    // We will always report this as a weak warning, matching the default behavior.
                    $errors[] = RuleErrorBuilder::message(
                        'Likely needs to be renamed in sake of maintainability (private property with the same name already defined in ' . $parentClassReflection->getDisplayName() . ').'
                    )->identifier('class.overridesPrivateField')->line($node->getStartLine())->build();
                    // The Java inspector returns after reporting private redefinition, so we do the same.
                    return $errors;
                }

                // Replicate Java inspector's logic for access level
                // Report only cases when access level is not changed (i.e., not weaker)
                $ownAccessLevel = $this->getAccessLevel($node);
                $parentAccessLevel = $this->getAccessLevelFromPropertyReflection($parentProperty);

                if ($ownAccessLevel <= $parentAccessLevel) {
                    $errors[] = RuleErrorBuilder::message(
                        'Field \'' . $propertyName . '\' is already defined in ' . $parentClassReflection->getDisplayName() . ', check our online documentation for options.'
                    )->identifier('class.overridesField')->line($node->getStartLine())->build();
                }
            }
        }

        return $errors;
    }

    private function getAccessLevel(Property $node): int
    {
        if ($node->isPrivate()) {
            return 0;
        }
        if ($node->isProtected()) {
            return 1;
        }
        return 2; // Public
    }

    private function getAccessLevelFromPropertyReflection(PropertyReflection $propertyReflection): int
    {
        if ($propertyReflection->isPrivate()) {
            return 0;
        }
        if (!$propertyReflection->isPublic()) {
            return 1; // Protected (neither private nor public)
        }
        return 2; // Public
    }

    /**
     * Check if this is a Laravel/Symfony Command framework pattern that should be allowed
     */
    private function isFrameworkCommandPattern(ClassReflection $childClass, ClassReflection $parentClass, string $propertyName): bool
    {
        // Laravel/Symfony Command pattern: allow protected $signature and $description 
        // even though parent Command class has private properties with same names
        $isCommandChild = $childClass->isSubclassOf('Symfony\Component\Console\Command\Command') ||
                         $childClass->isSubclassOf('Illuminate\Console\Command');
        
        $isCommandParent = $parentClass->getName() === 'Symfony\Component\Console\Command\Command' ||
                          $parentClass->isSubclassOf('Symfony\Component\Console\Command\Command');
        
        $isCommandProperty = in_array($propertyName, ['signature', 'description', 'name'], true);
        
        return $isCommandChild && $isCommandParent && $isCommandProperty;
    }
}
