<?php declare(strict_types=1);

// Trigger script for UnnecessaryAssertionRule
// This file contains unnecessary PHPUnit assertions that should be detected

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class UnnecessaryAssertionTrigger extends TestCase
{
    // Test functions with return types for type hint assertions
    public function returnsString(): string
    {
        return 'test';
    }

    public function returnsInt(): int
    {
        return 42;
    }

    public function returnsArray(): array
    {
        return [];
    }

    public function returnsNull(): null
    {
        return null;
    }

    public function testUnnecessaryTypeHintAssertions(): void
    {
        // These assertions should trigger because the function return type is already declared

        // Unnecessary: assertInstanceOf when function returns string
        $this->assertInstanceOf('string', $this->returnsString()); // Should trigger

        // Unnecessary: assertInternalType when function returns int
        $this->assertInternalType('int', $this->returnsInt()); // Should trigger

        // Unnecessary: assertEmpty when function returns array
        $this->assertEmpty($this->returnsArray()); // Should trigger

        // Unnecessary: assertNull when function returns null
        $this->assertNull($this->returnsNull()); // Should trigger
    }

    public function testNecessaryTypeHintAssertions(): void
    {
        // These assertions should NOT trigger because they check dynamic values

        $dynamicString = 'test';
        $this->assertInstanceOf('string', $dynamicString); // Should NOT trigger

        $dynamicInt = 42;
        $this->assertInternalType('int', $dynamicInt); // Should NOT trigger

        $dynamicArray = [];
        $this->assertEmpty($dynamicArray); // Should NOT trigger

        $dynamicNull = null;
        $this->assertNull($dynamicNull); // Should NOT trigger
    }

    public function testMockingAssertions(): void
    {
        // Create a mock
        $mock = $this->createMock(SomeInterface::class);

        // Unnecessary: ->expects(...->any()) can be omitted
        $mock->expects($this->any())->method('someMethod')->willReturn('value'); // Should trigger

        // Necessary: specific expectations should NOT trigger
        $mock->expects($this->once())->method('otherMethod')->willReturn('value'); // Should NOT trigger
        $mock->expects($this->atLeastOnce())->method('thirdMethod')->willReturn('value'); // Should NOT trigger
    }

    public function testValidMockingUsage(): void
    {
        $mock = $this->createMock(SomeInterface::class);

        // These should NOT trigger any warnings
        $mock->method('someMethod')->willReturn('value');
        $mock->method('otherMethod')->willReturn('value');
    }
}

interface SomeInterface
{
    public function someMethod(): string;
    public function otherMethod(): string;
    public function thirdMethod(): string;
}