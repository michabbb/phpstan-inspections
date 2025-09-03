<?php declare(strict_types=1);

// Trigger script for PhpUnitDeprecationsRule
// This file contains deprecated PHPUnit API usage patterns that should be detected

class TestCase extends \PHPUnit\Framework\TestCase
{
    public function testDeprecatedAssertEqualsArguments(): void
    {
        // Deprecated: assertEquals with $delta argument (4th parameter)
        $this->assertEquals(1.0, 1.0, 'message', 0.1); // Should trigger: $delta deprecated

        // Deprecated: assertNotEquals with $delta argument
        $this->assertNotEquals(1.0, 2.0, 'message', 0.1); // Should trigger: $delta deprecated

        // Deprecated: assertEquals with $maxDepth argument (5th parameter)
        $this->assertEquals([1], [1], 'message', null, 10); // Should trigger: $maxDepth deprecated

        // Deprecated: assertEquals with $canonicalize argument (6th parameter)
        $this->assertEquals([1, 2], [2, 1], 'message', null, null, true); // Should trigger: $canonicalize deprecated

        // Deprecated: assertEquals with $ignoreCase argument (7th parameter)
        $this->assertEquals('Hello', 'HELLO', 'message', null, null, false, true); // Should trigger: $ignoreCase deprecated

        // Deprecated: assertNotEquals with $ignoreCase argument
        $this->assertNotEquals('hello', 'HELLO', 'message', null, null, false, true); // Should trigger: $ignoreCase deprecated
    }

    public function testDeprecatedFileAssertionMethods(): void
    {
        // Deprecated: assertFileNotExists (should use assertFileDoesNotExist)
        $this->assertFileNotExists('/path/to/file.txt'); // Should trigger: method deprecated

        // Deprecated: assertDirectoryNotExists (should use assertDirectoryDoesNotExist)
        $this->assertDirectoryNotExists('/path/to/directory'); // Should trigger: method deprecated
    }

    public function testValidModernUsage(): void
    {
        // These should NOT trigger any deprecation warnings (modern usage)

        // Modern: assertEqualsWithDelta
        $this->assertEqualsWithDelta(1.0, 1.0, 0.1);

        // Modern: assertEqualsCanonicalizing
        $this->assertEqualsCanonicalizing([1, 2], [2, 1]);

        // Modern: assertEqualsIgnoringCase
        $this->assertEqualsIgnoringCase('Hello', 'HELLO');

        // Modern: assertFileDoesNotExist
        $this->assertFileDoesNotExist('/path/to/file.txt');

        // Modern: assertDirectoryDoesNotExist
        $this->assertDirectoryDoesNotExist('/path/to/directory');
    }
}