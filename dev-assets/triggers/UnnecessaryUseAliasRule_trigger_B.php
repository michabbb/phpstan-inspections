<?php declare(strict_types=1);

// This file should trigger the UnnecessaryUseAliasRule

// Test case: namespace alias that matches the last part
use Foo\Bar as Bar; // Should trigger: alias matches last part of namespace

// Test case: class alias that matches but in a context where it might be allowed
// Note: This might still cause PHP parsing issues

class ExampleClass
{
    public function test(): void
    {
        // Usage would depend on actual existence
    }
}