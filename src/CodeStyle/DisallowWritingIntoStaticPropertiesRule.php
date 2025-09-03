<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Disallows writing to static properties to prevent uncontrolled behavior.
 *
 * This rule prevents modification of static properties, which can lead to unpredictable
 * behavior in applications. It can be configured to either completely disallow static
 * property writes or only allow writes from within the declaring class itself.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\Assign>
 */
final class DisallowWritingIntoStaticPropertiesRule implements Rule
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly bool $allowWriteFromSourceClass
    ) {
    }

    public function getNodeType(): string
    {
        return Assign::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->var instanceof StaticPropertyFetch) {
            return [];
        }

        $class = $node->var->class;
        $property = $node->var->name;

        if (!$property instanceof Identifier) {
            return []; // Dynamic property name, cannot analyze
        }

        $propertyName = $property->name;
        $className = null;

        if ($class instanceof Node\Name) {
            $className = $scope->resolveName($class);
        } elseif ($class instanceof Node\Expr\Variable && $class->name === 'this') {
            // This case should not happen for StaticPropertyFetch, but for completeness
            return [];
        }

        if ($className === null) {
            return []; // Cannot resolve class name
        }

        if (!$this->reflectionProvider->hasClass($className)) {
            return []; // Class does not exist
        }

        $classReflection = $this->reflectionProvider->getClass($className);

        if (!$classReflection->hasProperty($propertyName)) {
            return []; // Property does not exist
        }

        $propertyReflection = $classReflection->getProperty($propertyName, $scope);

        if (!$propertyReflection->isStatic()) {
            return []; // Not a static property
        }

        // Case 1: Disallow any writes to static properties
        if (!$this->allowWriteFromSourceClass) {
            return [
                RuleErrorBuilder::message('Static properties should not be modified.')
                    ->identifier('staticProperty.write')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        // Case 2: Disallow external writes to static properties
        $declaringClass = $propertyReflection->getDeclaringClass();
        $currentClass = $scope->getClassReflection();

        if ($currentClass === null || $currentClass->getName() !== $declaringClass->getName()) {
            // Assignment is outside the declaring class or in a global/function scope
            return [
                RuleErrorBuilder::message('Static properties should be modified only inside the source class.')
                    ->identifier('staticProperty.externalWrite')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
