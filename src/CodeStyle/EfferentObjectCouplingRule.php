<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects high efferent coupling between objects.
 *
 * This rule identifies classes that depend on too many other classes, which can indicate
 * poor separation of concerns and make the code harder to maintain and test. Efferent coupling
 * measures the number of unique classes that a given class references.
 *
 * High coupling can lead to:
 * - Increased complexity and maintenance burden
 * - Tighter coupling between components
 * - Reduced testability and reusability
 * - Difficulty in understanding class responsibilities
 *
 * Consider refactoring classes with high efferent coupling by:
 * - Extracting interfaces or abstract base classes
 * - Using dependency injection to reduce direct dependencies
 * - Breaking down large classes into smaller, focused components
 * - Applying the Single Responsibility Principle
 *
 * @implements Rule<Class_>
 */
class EfferentObjectCouplingRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private int $couplingLimit = 20
    ) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        // Skip anonymous classes
        if ($node->name === null) {
            return [];
        }

        $classReferences = $this->findClassReferences($node, $scope);

        if ($classReferences >= $this->couplingLimit) {
            return [
                RuleErrorBuilder::message(
                    sprintf('High efferent coupling (%d). Consider refactoring to reduce dependencies.', $classReferences)
                )
                ->identifier('coupling.efferentObjectCoupling')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        return [];
    }

    private function findClassReferences(Class_ $classNode, Scope $scope): int
    {
        $nodeFinder = new NodeFinder();
        $nameNodes = $nodeFinder->findInstanceOf($classNode, Name::class);

        $uniqueClassNames = [];

        foreach ($nameNodes as $nameNode) {
            // Skip if this is a function call, method call, or other non-class reference
            if (!$this->isClassReference($nameNode, $scope)) {
                continue;
            }

            // Get the fully qualified name
            $fqn = $this->resolveFQN($nameNode, $scope);
            if ($fqn !== null) {
                $uniqueClassNames[$fqn] = true;
            }
        }

        return count($uniqueClassNames);
    }

    private function isClassReference(Name $nameNode, Scope $scope): bool
    {
        // Check if this name refers to a class/interface/trait
        $fqn = $this->resolveFQN($nameNode, $scope);
        if ($fqn === null) {
            return false;
        }

        // Use reflection provider to check if it's a class-like construct
        return $this->reflectionProvider->hasClass($fqn) &&
               ($this->reflectionProvider->getClass($fqn)->isClass() ||
                $this->reflectionProvider->getClass($fqn)->isInterface() ||
                $this->reflectionProvider->getClass($fqn)->isTrait());
    }

    private function resolveFQN(Name $nameNode, Scope $scope): ?string
    {
        try {
            // Try to resolve the name in the current scope
            $resolvedName = $scope->resolveName($nameNode);
            return $resolvedName;
        } catch (\Throwable $e) {
            // If resolution fails, return null
            return null;
        }
    }
}