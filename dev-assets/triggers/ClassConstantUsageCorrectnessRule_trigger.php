<?php declare(strict_types=1);

namespace TestNamespace;

// Test class for case mismatch detection
class TestClass
{
    public function testCorrectCase(): void
    {
        // These should NOT trigger the rule (correct case)
        $className1 = TestClass::class; // Correct case
        $className2 = \TestNamespace\TestClass::class; // Correct case with namespace
        $className3 = self::class; // Correct (self reference)
        $className4 = static::class; // Correct (static reference)
    }

    public function testIncorrectCase(): void
    {
        // These should trigger the rule (case mismatch)
        $className1 = testclass::class; // Incorrect case - lowercase
        $className2 = TestCLASS::class; // Incorrect case - mixed case
        $className3 = TESTCLASS::class; // Incorrect case - uppercase
        $className4 = \testnamespace\TestClass::class; // Incorrect namespace case
        $className5 = \TestNamespace\testclass::class; // Incorrect class case
    }
}

// Test with imports and aliases
use TestNamespace\TestClass as AliasedClass;

class TestWithImports
{
    public function testWithAlias(): void
    {
        // These should NOT trigger (correct usage)
        $className1 = AliasedClass::class; // Correct alias usage
        $className2 = TestClass::class; // Correct class name

        // These should trigger (case mismatch)
        $className3 = aliasedclass::class; // Incorrect alias case
        $className4 = testclass::class; // Incorrect class case
    }
}

// Test with fully qualified names
class TestFullyQualified
{
    public function testFQCN(): void
    {
        // These should NOT trigger (correct case)
        $className1 = \TestNamespace\TestClass::class; // Correct FQCN

        // These should trigger (case mismatch)
        $className2 = \testnamespace\TestClass::class; // Incorrect namespace case
        $className3 = \TestNamespace\testclass::class; // Incorrect class case
        $className4 = \TESTNAMESPACE\TESTCLASS::class; // All uppercase
    }
}