<?php declare(strict_types=1);

// Test file for ClassMockingCorrectnessRule
// This file contains examples that should trigger the rule

// Test classes and traits
final class FinalClass
{
    public function method(): void {}
}

trait TestTrait
{
    public function traitMethod(): void {}
}

abstract class AbstractClass
{
    public function concreteMethod(): void {}
    abstract public function abstractMethod(): void;
}

class ClassWithConstructor
{
    public function __construct(private string $param) {}
}

class RegularClass
{
    public function method(): void {}
}

// PHPUnit Test Case Examples

class TestCase extends \PHPUnit\Framework\TestCase
{
    // Positive case 1: Final class with createMock should trigger
    public function testFinalClassWithCreateMock(): void
    {
        $mock = $this->createMock(FinalClass::class); // Should trigger: final class
    }

    // Positive case 2: Trait with createMock should trigger
    public function testTraitWithCreateMock(): void
    {
        $mock = $this->createMock(TestTrait::class); // Should trigger: trait
    }

    // Positive case 3: Final class with getMockBuilder should trigger
    public function testFinalClassWithGetMockBuilder(): void
    {
        $mock = $this->getMockBuilder(FinalClass::class)->getMock(); // Should trigger: final class
    }

    // Positive case 4: Trait with getMockBuilder should trigger
    public function testTraitWithGetMockBuilder(): void
    {
        $mock = $this->getMockBuilder(TestTrait::class)->getMock(); // Should trigger: trait
    }

    // Positive case 5: Abstract class with getMockBuilder should suggest getMockForAbstractClass
    public function testAbstractClassWithGetMockBuilder(): void
    {
        $mock = $this->getMockBuilder(AbstractClass::class)->getMock(); // Should trigger: use getMockForAbstractClass
    }

    // Positive case 6: Constructor parameters not handled
    public function testConstructorParamsNotHandled(): void
    {
        $mock = $this->getMockBuilder(ClassWithConstructor::class)->getMock(); // Should trigger: constructor params
    }

    // Positive case 7: getMockForTrait with non-trait should trigger
    public function testGetMockForTraitWithNonTrait(): void
    {
        $mock = $this->getMockForTrait(RegularClass::class); // Should trigger: not a trait
    }

    // Positive case 8: getMockForAbstractClass with non-abstract should trigger
    public function testGetMockForAbstractClassWithNonAbstract(): void
    {
        $mock = $this->getMockForAbstractClass(RegularClass::class); // Should trigger: not abstract
    }

    // Negative case: Correct usage should not trigger
    public function testCorrectUsage(): void
    {
        $mock = $this->createMock(RegularClass::class); // OK: regular class
        $traitMock = $this->getMockForTrait(TestTrait::class); // OK: trait with correct method
        $abstractMock = $this->getMockForAbstractClass(AbstractClass::class); // OK: abstract with correct method
        $constructorMock = $this->getMockBuilder(ClassWithConstructor::class)
            ->disableOriginalConstructor()
            ->getMock(); // OK: constructor disabled
    }
}

// PhpSpec Examples
class ObjectBehaviorSpec extends \PhpSpec\ObjectBehavior
{
    // Positive case 9: Final class in method parameter should trigger
    public function it_should_do_something(FinalClass $finalClass): void // Should trigger: final class parameter
    {
        // Test code
    }

    // Negative case: Regular class parameter should not trigger
    public function it_should_do_something_else(RegularClass $regularClass): void // OK: regular class
    {
        // Test code
    }
}