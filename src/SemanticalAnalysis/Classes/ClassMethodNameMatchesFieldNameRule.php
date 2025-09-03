<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\CallableType;
use PHPStan\Type\MixedType;

/**
 * Detects when a class method name matches an existing field name.
 *
 * This rule identifies methods in classes that have the same name as a field in the same class.
 * This can be particularly confusing when the field stores a callable type, as it may lead to
 * ambiguous calls like $this->fieldOrMethod() which could be intended as ($this->fieldOrMethod)().
 *
 * The rule reports two scenarios:
 * - When a field with the same name exists but its type cannot be resolved
 * - When a field with the same name exists and its type is callable
 *
 * @implements Rule<ClassMethod>
 */
final class ClassMethodNameMatchesFieldNameRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || $classReflection->isInterface() || $classReflection->isAnonymous()) {
            return [];
        }

        $methodName = $node->name->toString();

        // Check if there's a property with the same name
        if (!$classReflection->hasProperty($methodName)) {
            return [];
        }

        $propertyReflection = $classReflection->getProperty($methodName, $scope);

        // Skip static properties
        if ($propertyReflection->isStatic()) {
            return [];
        }

        $propertyType = $propertyReflection->getReadableType();

        // Check if the property type is unknown/unresolved
        if ($propertyType instanceof MixedType && $propertyType->isExplicitMixed()) {
            return [
                RuleErrorBuilder::message(
                    'There is a field with the same name, but its type cannot be resolved.'
                )
                    ->identifier('methodName.matchesFieldName')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        // Check if the property type is callable
        if ($propertyType instanceof CallableType) {
            return [
                RuleErrorBuilder::message(
                    'There is a field with the same name, please give the method another name like is*, get*, set* and etc.'
                )
                    ->identifier('methodName.matchesFieldName')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}