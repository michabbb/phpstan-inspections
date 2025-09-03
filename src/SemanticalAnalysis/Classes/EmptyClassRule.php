<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects empty classes that do not contain any properties or methods.
 *
 * This rule identifies:
 * - Classes with no properties, methods, traits, or enum cases
 * - Enums with no values or methods
 *
 * Exceptions are made for:
 * - Abstract parent classes (may require empty implementations)
 * - Classes inheriting from Exception (common pattern)
 * - Interfaces, deprecated classes, and anonymous classes
 *
 * @implements Rule<Class_>
 */
class EmptyClassRule implements Rule
{
    private const string MESSAGE_CLASS = 'Class does not contain any properties or methods.';
    private const string MESSAGE_ENUM = 'Enum does not contain any values or methods.';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
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

        // Skip interfaces, deprecated classes, abstract classes, and anonymous classes
        if ($this->shouldSkipClass($node, $scope)) {
            return [];
        }

        // Check if class is empty
        if (!$this->isEmptyClass($node, $scope)) {
            return [];
        }

        // Check for exceptions (abstract parent or Exception inheritance)
        if ($this->hasExceptionalInheritance($node, $scope)) {
            return [];
        }

        // Only handle classes for now, not enums
        $message = self::MESSAGE_CLASS;
        $identifier = 'class.empty';

        return [
            RuleErrorBuilder::message($message)
                ->identifier($identifier)
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function shouldSkipClass(Class_ $node, Scope $scope): bool
    {
        // Skip anonymous classes
        if ($node->isAnonymous()) {
            return true;
        }

        // Skip abstract classes
        if ($node->isAbstract()) {
            return true;
        }

        // Skip deprecated classes (check for @deprecated in doc comment)
        if ($node->getDocComment() !== null) {
            $docComment = $node->getDocComment()->getText();
            if (str_contains($docComment, '@deprecated')) {
                return true;
            }
        }

        return false;
    }

    private function isEmptyClass(Class_ $node, Scope $scope): bool
    {
        // Check for properties (including promoted constructor properties)
        if (!empty($node->getProperties())) {
            return false;
        }

        // Check for constants - matches Java original clazz.getOwnFields() behavior
        if (!empty($node->getConstants())) {
            return false;
        }

        // Check for promoted properties in constructor
        foreach ($node->getMethods() as $method) {
            if (strtolower($method->name->toString()) === '__construct') {
                foreach ($method->params as $param) {
                    if (($param->flags & (Class_::MODIFIER_PUBLIC | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PRIVATE)) !== 0) {
                        return false;
                    }
                }
            }
        }

        // Methods handling - count ALL methods (including magic methods like __invoke)
        // Java original checks getOwnMethods().length == 0, which includes all methods
        $allMethods = $node->getMethods();
        if (!empty($allMethods)) {
            return false; // has any methods
        }

        // Check for traits
        if (!empty($node->getTraitUses())) {
            return false;
        }

        // Skip enum processing for now - only handle classes

        return true;
    }

    private function hasExceptionalInheritance(Class_ $node, Scope $scope): bool
    {
        // Check if class name suggests it should be treated as having a valid purpose
        if ($node->name instanceof \PhpParser\Node\Identifier) {
            $className = $node->name->toString();
            
            // Get the fully qualified name for reflection
            $fqn = $scope->getNamespace() !== null
                ? $scope->getNamespace() . '\\' . $className
                : $className;
                
            if ($this->reflectionProvider->hasClass($fqn)) {
                $classReflection = $this->reflectionProvider->getClass($fqn);
                
                // Check the entire inheritance chain for Exception or Throwable
                $parents = $classReflection->getParents();
                foreach ($parents as $parent) {
                    $parentFqn = strtolower($parent->getName());
                    if ($parentFqn === 'exception' || str_ends_with($parentFqn, '\\exception') ||
                        $parentFqn === 'throwable' || str_ends_with($parentFqn, '\\throwable')) {
                        return true;
                    }
                }
                
                // Also check if the parent class is abstract (Java original logic)
                if (!empty($parents)) {
                    foreach ($parents as $parent) {
                        if ($parent->isAbstract()) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function getInheritanceChain(\PHPStan\Reflection\ClassReflection $classReflection): array
    {
        $chain = [];

        // Add parent classes
        foreach ($classReflection->getParents() as $parent) {
            $chain[] = $parent->getName();
            $chain = array_merge($chain, $this->getInheritanceChain($parent));
        }

        // Add interfaces
        foreach ($classReflection->getInterfaces() as $interface) {
            $chain[] = $interface->getName();
        }

        return $chain;
    }

    private static function isMagicMethodName(string $name): bool
    {
        return in_array($name, [
            '__construct', '__destruct', '__call', '__callstatic', '__get', '__set', '__isset', '__unset',
            '__sleep', '__wakeup', '__tostring', '__invoke', '__set_state', '__clone', '__debuginfo',
        ], true);
    }
}
