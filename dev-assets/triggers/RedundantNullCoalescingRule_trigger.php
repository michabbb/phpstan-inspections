<?php declare(strict_types=1);

// Test file for RedundantNullCoalescingRule
// This file contains examples that should trigger the rule

class TestClass {
    public function getNonNullableArray(): array {
        return [1, 2, 3];
    }

    public function getNonNullableString(): string {
        return "hello";
    }

    public function getNonNullableInt(): int {
        return 42;
    }
}

function getNonNullableArray(): array {
    return ['a', 'b', 'c'];
}

function getNonNullableString(): string {
    return "world";
}

function getNonNullableInt(): int {
    return 123;
}

// Positive cases - should trigger the rule (redundant ?? [])

$test = new TestClass();

// Case 1: Non-nullable array function ?? []
$result1 = getNonNullableArray() ?? [];

// Case 2: Non-nullable array method ?? []
$result2 = $test->getNonNullableArray() ?? [];

// Case 3: Non-nullable string function ?? [] (left cannot be null)
$result3 = getNonNullableString() ?? [];

// Case 4: Non-nullable string method ?? [] (left cannot be null)
$result4 = $test->getNonNullableString() ?? [];

// Case 5: Non-nullable int function ?? [] (left cannot be null)
$result5 = getNonNullableInt() ?? [];

// Case 6: Non-nullable int method ?? [] (left cannot be null)
$result6 = $test->getNonNullableInt() ?? [];

// Case 7: Variable assigned to non-nullable array ?? []
$arrayVar = [1, 2, 3];
$result7 = $arrayVar ?? [];

// Case 8: Variable assigned to non-nullable string ?? []
$stringVar = "test";
$result8 = $stringVar ?? [];

// Case 9: Variable assigned to non-nullable int ?? []
$intVar = 456;
$result9 = $intVar ?? [];

// Case 10: Static method returning non-nullable array ?? []
$result10 = TestClass::getNonNullableArray() ?? [];

// Negative cases - should NOT trigger the rule

// Case 11: Nullable array ?? [] (should not trigger - left can be null)
$nullableArray = null;
$result11 = $nullableArray ?? [];

// Case 12: Function that might return null ?? [] (should not trigger)
function getMaybeNullArray(): ?array {
    return rand(0, 1) ? [1, 2] : null;
}
$result12 = getMaybeNullArray() ?? [];

// Case 13: ?? with non-empty array (should not trigger)
$result13 = getNonNullableArray() ?? [1, 2, 3];

// Case 14: ?? with string default (should not trigger)
$result14 = getNonNullableArray() ?? "default";

// Case 15: ?? with null default (should not trigger)
$result15 = getNonNullableArray() ?? null;