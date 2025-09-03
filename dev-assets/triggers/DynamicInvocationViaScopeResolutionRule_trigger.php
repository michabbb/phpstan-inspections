<?php declare(strict_types=1);

// Test file for DynamicInvocationViaScopeResolutionRule
// This file contains examples that should trigger the rule

class TestClass
{
    public function instanceMethod(): void
    {
        echo "Instance method called\n";
    }

    public function anotherInstanceMethod(): void
    {
        echo "Another instance method\n";
    }

    public static function staticMethod(): void
    {
        echo "Static method called\n";
    }

    // This should trigger: static::instanceMethod() in instance method
    public function testStaticCall(): void
    {
        static::instanceMethod(); // Should trigger: use $this->instanceMethod()
    }

    // This should trigger: self::instanceMethod() in instance method
    public function testSelfCall(): void
    {
        self::instanceMethod(); // Should trigger: use $this->instanceMethod()
    }

    // This should trigger: TestClass::instanceMethod() in instance method
    public function testClassNameCall(): void
    {
        TestClass::instanceMethod(); // Should trigger: use $this->instanceMethod()
    }

    // This should NOT trigger: static::staticMethod() is correct
    public function testValidStaticCall(): void
    {
        static::staticMethod(); // Valid: static method called statically
    }

    // This should NOT trigger: self::staticMethod() is correct
    public static function testValidSelfStaticCall(): void
    {
        self::staticMethod(); // Valid: static method called statically
    }
}

// Test with object variable
class ObjectTest
{
    public function instanceMethod(): void
    {
        echo "Object instance method\n";
    }

    public function testObjectCall(): void
    {
        $obj = new ObjectTest();

        // This should trigger: $obj::instanceMethod() should be $obj->instanceMethod()
        $obj::instanceMethod(); // Should trigger: use $obj->instanceMethod()
    }

    public function testValidObjectCall(): void
    {
        $obj = new ObjectTest();
        $obj->instanceMethod(); // Valid: instance method called on object
    }
}

// Abstract class - should not trigger
abstract class AbstractTest
{
    abstract public function abstractMethod(): void;

    public function instanceMethod(): void
    {
        echo "Instance method in abstract class\n";
    }

    public function testAbstractCall(): void
    {
        // This should NOT trigger: abstract methods can be called statically
        static::abstractMethod(); // Valid: abstract method
    }
}

// Interface - should not trigger
interface TestInterface
{
    public function interfaceMethod(): void;
}

class InterfaceImplementation implements TestInterface
{
    public function interfaceMethod(): void
    {
        echo "Interface method implementation\n";
    }

    public function testInterfaceCall(): void
    {
        // This should NOT trigger: interface methods can be called statically
        static::interfaceMethod(); // Valid: interface method
    }
}