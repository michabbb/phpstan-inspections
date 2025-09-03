<?php

declare(strict_types=1);

// Test cases for DisallowWritingIntoStaticPropertiesRule

class TestClass
{
    public static string $publicStaticProperty = 'default';
    private static int $privateStaticProperty = 42;
    protected static array $protectedStaticProperty = [];

    public function writeToOwnStaticProperty(): void
    {
        // This should NOT trigger if allowWriteFromSourceClass is true
        self::$privateStaticProperty = 100;
        static::$publicStaticProperty = 'modified';
        TestClass::$protectedStaticProperty = ['key' => 'value'];
    }

    public function writeToOtherClassStaticProperty(): void
    {
        // This should trigger - writing to another class's static property
        OtherClass::$staticProperty = 'external write';
    }
}

class OtherClass
{
    public static string $staticProperty = 'original';

    public function writeToOwnStaticProperty(): void
    {
        // This should NOT trigger if allowWriteFromSourceClass is true
        self::$staticProperty = 'internal write';
    }
}

// Global function - should trigger when writing to static properties
function globalFunctionWriteToStatic(): void
{
    TestClass::$publicStaticProperty = 'global write';
    OtherClass::$staticProperty = 'global write to other';
}

// Test inheritance scenarios
class ChildClass extends TestClass
{
    public function writeToParentStaticProperty(): void
    {
        // This should trigger - writing to parent class static property from child
        parent::$publicStaticProperty = 'child write';
        TestClass::$privateStaticProperty = 200; // Should trigger - accessing private from child
    }
}

// Test with dynamic class names (should not trigger as rule cannot analyze)
function dynamicClassWrite(): void
{
    $className = 'TestClass';
    $className::$publicStaticProperty = 'dynamic'; // Should not trigger - dynamic
}

// Test with variable property names (should not trigger as rule cannot analyze)
function dynamicPropertyWrite(): void
{
    $property = 'publicStaticProperty';
    TestClass::$$property = 'dynamic property'; // Should not trigger - dynamic
}

// Edge case: Writing to non-existent static property (should not trigger)
function writeToNonExistentStatic(): void
{
    TestClass::$nonExistentProperty = 'value'; // Should not trigger - property doesn't exist
}

// Test with fully qualified class names
function fullyQualifiedWrite(): void
{
    \TestClass::$publicStaticProperty = 'qualified write'; // Should trigger
}