<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects when a class implements an interface that is already implemented by its parent class.
 *
 * This rule identifies redundant interface implementations in class hierarchies:
 * - Child classes that explicitly implement interfaces already implemented by their parent
 * - Helps maintain clean inheritance hierarchies and avoid unnecessary code duplication
 *
 * @implements Rule<Class_>
 */
class ClassReImplementsParentInterfaceRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        // Skip anonymous classes
        if ($node->name === null) {
            return [];
        }

        // Get the implements list
        $implements = $node->implements;
        if (empty($implements)) {
            return [];
        }

        // Get the parent class
        if ($node->extends === null) {
            return [];
        }

        $parentClassName = $node->extends->toString();
        if (!$this->reflectionProvider->hasClass($parentClassName)) {
            return [];
        }

        $parentClassReflection = $this->reflectionProvider->getClass($parentClassName);

        // Get all interfaces implemented by the parent class (including inherited ones)
        $parentInterfaces = $this->getAllInterfaces($parentClassReflection);

        $errors = [];
        foreach ($implements as $interfaceNode) {
            $interfaceName = $interfaceNode->toString();

            if (isset($parentInterfaces[$interfaceName])) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        "'%s' is already announced in '%s'.",
                        $interfaceName,
                        $parentClassName
                    )
                )
                    ->identifier('class.reimplementsParentInterface')
                    ->line($interfaceNode->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * Get all interfaces implemented by a class, including inherited ones.
     *
     * @return array<string, true>
     */
    private function getAllInterfaces(\PHPStan\Reflection\ClassReflection $classReflection): array
    {
        $interfaces = [];

        // Add direct interfaces
        foreach ($classReflection->getInterfaces() as $interface) {
            $interfaces[$interface->getName()] = true;
        }

        // Add interfaces from parent classes
        foreach ($classReflection->getParents() as $parent) {
            foreach ($this->getAllInterfaces($parent) as $interfaceName => $value) {
                $interfaces[$interfaceName] = true;
            }
        }

        return $interfaces;
    }
}