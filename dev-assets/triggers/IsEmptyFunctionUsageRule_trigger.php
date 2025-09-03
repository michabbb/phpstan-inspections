<?php

declare(strict_types=1);

// Test cases for IsEmptyFunctionUsageRule

class TestClass implements Countable
{
    public function count(): int
    {
        return 0;
    }
}

function testEmptyUsage(): void
{
    $array          = [];
    $nullableString = null;
    $nullableInt    = 42;
    $object         = new TestClass();
    $property       = 'test';
    
    // Should trigger count comparison suggestion
    if (empty($array)) {
        echo 'Array is empty';
    }
    
    // Should trigger count comparison suggestion for countable object
    if (empty($object)) {
        echo 'Object is empty';
    }
    
    // Should trigger null comparison suggestion for nullable string
    if (empty($nullableString)) {
        echo 'String is null or empty';
    }
    
    // Should trigger null comparison suggestion for nullable int
    if (empty($nullableInt)) {
        echo 'Int is null or empty';
    }
    
    // Should NOT trigger for field reference (property access)
    $obj        = new stdClass();
    $obj->field = 'value';
    if (empty($obj->field)) {
        echo 'Field is empty';
    }
    
    // Should work with inverted conditions (!empty)
    if (!empty($array)) {
        echo 'Array is not empty';
    }
    
    if (!empty($nullableString)) {
        echo 'String is not null/empty';
    }
    
    // Array access should be skipped
    $data = ['key' => 'value'];
    if (empty($data['key'])) {
        echo 'Key is empty';
    }
}

// Mixed types for testing
function testMixedTypes(?array $nullableArray, ?int $nullableInt, ?bool $nullableBool): void
{
    // Should suggest count comparison for nullable array
    if (empty($nullableArray)) {
        echo 'Nullable array is empty';
    }
    
    // Should suggest null comparison for nullable int
    if (empty($nullableInt)) {
        echo 'Nullable int is empty';
    }
    
    // Should suggest null comparison for nullable bool
    if (empty($nullableBool)) {
        echo 'Nullable bool is empty';
    }
}

function testNonNullableTypes(string $string, int $int, bool $bool): void
{
    // These should not trigger suggestions since they're not nullable
    if (empty($string)) {
        echo 'String is empty';
    }
    
    if (empty($int)) {
        echo 'Int is empty';
    }
    
    if (empty($bool)) {
        echo 'Bool is empty';
    }
}
