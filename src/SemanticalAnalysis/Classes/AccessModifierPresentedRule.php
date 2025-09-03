<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\ClassConst;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects class members (methods, properties, constants) that are public but do not have the 'public' keyword explicitly declared.
 *
 * This rule identifies:
 * - Methods that are public but rely on default visibility without explicit 'public' modifier
 * - Properties that are public but lack explicit 'public' modifier
 * - Constants that are public but lack explicit 'public' modifier (PHP 7.1+)
 *
 * These cases should be made explicit by adding the 'public' keyword for better code clarity and consistency.
 *
 * @implements Rule<Class_>
 */
class AccessModifierPresentedRule implements Rule
{
    private bool $analyzeInterfaces;
    private bool $analyzeConstants;

    public function __construct(bool $analyzeInterfaces = true, bool $analyzeConstants = true)
    {
        $this->analyzeInterfaces = $analyzeInterfaces;
        $this->analyzeConstants = $analyzeConstants;
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        // Skip interfaces if not configured to analyze them
        if (!$this->analyzeInterfaces && $node->isInterface()) {
            return [];
        }

        $errors = [];

        // Check methods
        foreach ($node->getMethods() as $method) {
            $methodErrors = $this->checkMethod($method);
            $errors = array_merge($errors, $methodErrors);
        }

        // Check properties
        foreach ($node->getProperties() as $property) {
            $propertyErrors = $this->checkProperty($property);
            $errors = array_merge($errors, $propertyErrors);
        }

        // Check constants
        foreach ($node->getConstants() as $constant) {
            $constantErrors = $this->checkConstant($constant, $scope);
            $errors = array_merge($errors, $constantErrors);
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkMethod(ClassMethod $method): array
    {
        // Skip if method is not public
        if (!$method->isPublic()) {
            return [];
        }

        // Check if the method has explicit public modifier
        if (!$this->hasExplicitPublicModifier($method->flags)) {
            return [
                RuleErrorBuilder::message(
                    sprintf("'%s' should be declared with access modifier.", $method->name->toString())
                )
                ->identifier('accessModifier.missing')
                ->line($method->getStartLine())
                ->build(),
            ];
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkProperty(Property $property): array
    {
        // Skip if property is not public
        if (!$property->isPublic()) {
            return [];
        }

        // Check if the property has explicit public modifier
        if (!$this->hasExplicitPublicModifier($property->flags)) {
            $propertyName = $property->props[0]->name->toString();
            return [
                RuleErrorBuilder::message(
                    sprintf("'%s' should be declared with access modifier.", $propertyName)
                )
                ->identifier('accessModifier.missing')
                ->line($property->getStartLine())
                ->build(),
            ];
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkConstant(ClassConst $constant, Scope $scope): array
    {
        // Skip constants if not configured to analyze them
        if (!$this->analyzeConstants) {
            return [];
        }

        // Check if PHP version supports constant visibility (7.1+)
        if (!$this->supportsConstantVisibility($scope)) {
            return [];
        }

        // Skip if constant is not public
        if (!$constant->isPublic()) {
            return [];
        }

        // Check if the constant has explicit public modifier
        if (!$this->hasExplicitPublicModifier($constant->flags)) {
            $constantName = $constant->consts[0]->name->toString();
            return [
                RuleErrorBuilder::message(
                    sprintf("'%s' should be declared with access modifier.", $constantName)
                )
                ->identifier('accessModifier.missing')
                ->line($constant->getStartLine())
                ->build(),
            ];
        }

        return [];
    }

    private function hasExplicitPublicModifier(int $flags): bool
    {
        return ($flags & 1) === 1; // 1 = public modifier flag
    }

    private function supportsConstantVisibility(Scope $scope): bool
    {
        // PHP 7.1+ supports constant visibility
        return PHP_VERSION_ID >= 70100;
    }
}