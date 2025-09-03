<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects class properties that could be static based on their initialization being "heavy".
 *
 * This rule identifies properties that:
 * - Are non-constant, non-static, and non-public
 * - Have array initialization with at least 3 nested arrays or string literals
 * - Are not inherited from parent classes
 *
 * Such properties should be made static to avoid unnecessary object instantiation overhead.
 * If PHP 5.6+ is available, constants may also be considered as an alternative.
 *
 * @implements Rule<Property>
 */
class PropertyCanBeStaticRule implements Rule
{
    private const string MESSAGE_NO_CONSTANTS = 'This property initialization seems to be quite \'heavy\', consider using static property instead.';
    private const string MESSAGE_WITH_CONSTANTS = 'This property initialization seems to be quite \'heavy\', consider using static property or constant instead.';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return Property::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Property) {
            return [];
        }

        // Skip if not in a class context
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        // Check each property in the property statement
        $errors = [];
        foreach ($node->props as $property) {
            $error = $this->analyzeProperty($property, $node, $scope, $classReflection);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private function analyzeProperty(
        PropertyProperty $property,
        Property $propertyNode,
        Scope $scope,
        \PHPStan\Reflection\ClassReflection $classReflection
    ): ?\PHPStan\Rules\RuleError {
        // Skip constants (this rule only applies to properties, not constants)
        // Constants are handled by ClassConst nodes, not Property nodes

        // Skip static properties
        if ($propertyNode->isStatic()) {
            return null;
        }

        // Skip public properties
        if ($propertyNode->isPublic()) {
            return null;
        }

        // Check if property is inherited from parent
        if ($this->isInheritedProperty($property->name->toString(), $classReflection)) {
            return null;
        }

        // Check for @noinspection suppression
        if ($this->isSuppressed($propertyNode)) {
            return null;
        }

        // Check default value
        $defaultValue = $property->default;
        if (!$defaultValue instanceof Array_) {
            return null;
        }

        // Count heavy elements (nested arrays or strings)
        $heavyCount = $this->countHeavyElements($defaultValue);
        if ($heavyCount < 3) {
            return null;
        }

        // Determine if constants are available (PHP 5.6+)
        $canUseConstants = $this->canUseConstants($scope);

        $message = $canUseConstants ? self::MESSAGE_WITH_CONSTANTS : self::MESSAGE_NO_CONSTANTS;
        $identifier = 'property.canBeStatic';

        return RuleErrorBuilder::message($message)
            ->identifier($identifier)
            ->line($property->getStartLine())
            ->build();
    }

    private function isInheritedProperty(string $propertyName, \PHPStan\Reflection\ClassReflection $classReflection): bool
    {
        // Check parent classes
        foreach ($classReflection->getParents() as $parent) {
            if ($parent->hasProperty($propertyName)) {
                return true;
            }
        }

        return false;
    }

    private function isSuppressed(Property $propertyNode): bool
    {
        // Check for @noinspection comment above the property
        $comments = $propertyNode->getComments();
        foreach ($comments as $comment) {
            $text = $comment->getText();
            if (str_contains($text, '@noinspection') &&
                str_contains($text, 'PropertyCanBeStaticInspection')) {
                return true;
            }
        }

        return false;
    }

    private function countHeavyElements(Array_ $array): int
    {
        $count = 0;

        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            $value = $item->value;

            // Count nested arrays
            if ($value instanceof Array_) {
                $count++;
            }
            // Count string literals
            elseif ($value instanceof String_) {
                $count++;
            }
        }

        return $count;
    }

    private function canUseConstants(Scope $scope): bool
    {
        // PHPStan doesn't directly provide PHP version info, but we can assume
        // modern PHP versions support constants. For simplicity, we'll always
        // suggest constants as an option since this is a PHP 8.4 project.
        return true;
    }
}