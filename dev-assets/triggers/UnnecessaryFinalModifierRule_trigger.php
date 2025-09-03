<?php declare(strict_types=1);

/**
 * Trigger script for UnnecessaryFinalModifierRule
 *
 * This script contains test cases that should trigger the rule:
 * - Final methods in final classes (redundant)
 * - Private final methods (redundant)
 *
 * And cases that should NOT trigger:
 * - Public/protected final methods in non-final classes
 */

// Case 1: Final class with final methods (should trigger - redundant)
final class FinalClassWithFinalMethods
{
    // This should trigger: final method in final class
    final public function publicFinalMethod(): void
    {
        echo "This is final but unnecessary";
    }

    // This should trigger: final method in final class
    final protected function protectedFinalMethod(): void
    {
        echo "This is final but unnecessary";
    }

    // This should trigger: private final method
    final private function privateFinalMethod(): void
    {
        echo "This is final but unnecessary";
    }
}

// Case 2: Non-final class with private final methods (should trigger)
class NonFinalClassWithPrivateFinalMethods
{
    // This should trigger: private final method
    final private function privateFinalMethod(): void
    {
        echo "This is final but unnecessary";
    }

    // This should NOT trigger: public final method in non-final class
    final public function publicFinalMethod(): void
    {
        echo "This final is necessary";
    }

    // This should NOT trigger: protected final method in non-final class
    final protected function protectedFinalMethod(): void
    {
        echo "This final is necessary";
    }

    // This should NOT trigger: regular private method (no final)
    private function regularPrivateMethod(): void
    {
        echo "This is not final";
    }
}

// Case 3: Magic methods (should NOT trigger even if private and final)
class ClassWithMagicMethods
{
    // This should NOT trigger: magic method starting with __
    final private function __construct()
    {
        echo "Magic constructor";
    }

    // This should NOT trigger: magic method starting with __
    final private function __destruct()
    {
        echo "Magic destructor";
    }

    // This should NOT trigger: magic method starting with __
    final private function __toString(): string
    {
        return "Magic toString";
    }
}

// Case 4: Abstract class (should NOT trigger)
abstract class AbstractClass
{
    // This should NOT trigger: abstract methods can't be final
    abstract public function abstractMethod(): void;

    // This should NOT trigger: concrete method in abstract class
    public function concreteMethod(): void
    {
        echo "Concrete method";
    }
}

// Case 5: Interface (should NOT trigger - interfaces don't have final methods)
interface TestInterface
{
    public function interfaceMethod(): void;
}

class ImplementingClass implements TestInterface
{
    // This should NOT trigger: implementing interface method
    public function interfaceMethod(): void
    {
        echo "Implementing interface";
    }
}