<?php declare(strict_types=1);

// Test file for ProperNullCoalescingOperatorUsageRule
// This file contains only examples that should trigger the rule

class TestClass {
    public function getData(): ?array {
        return null;
    }

    public function getString(): ?string {
        return null;
    }

    public function getInt(): int {
        return 42;
    }
}

function getNullableString(): ?string {
    return null;
}

function getString(): string {
    return 'hello';
}

// Cases that should trigger the rule

// Function call ?? null - should suggest simplification
$result1 = getNullableString() ?? null;

// Method call ?? null - should suggest simplification  
$test = new TestClass();
$result2 = $test->getData() ?? null;

// Static method call ?? null - should suggest simplification
$result3 = TestClass::getString() ?? null;

// Type mismatch - string ?? int (not complementary)
$result4 = getString() ?? 42;

// Type mismatch - int ?? array (not complementary)
$result5 = $test->getInt() ?? [];