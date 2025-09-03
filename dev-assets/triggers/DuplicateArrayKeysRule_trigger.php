<?php

// Positive cases - should trigger errors

// Case 1: Duplicate key with different values
$array1 = [
    'key1' => 'value1',
    'key2' => 'value2',
    'key1' => 'value3', // Duplicate key with different value
];

// Case 2: Duplicate key-value pair
$array2 = [
    'key1' => 'value1',
    'key2' => 'value2',
    'key1' => 'value1', // Duplicate key-value pair
];

// Case 2b: Duplicate key with different value
$array2b = [
    'key1' => 'value1',
    'key2' => 'value2',
    'key1' => 'different_value', // Duplicate key with different value
];

// Case 3: Multiple duplicates
$array3 = [
    'a' => 1,
    'b' => 2,
    'a' => 3, // Different value
    'b' => 2, // Same value
    'c' => 4,
];

// Case 4: Complex expressions as values
$array4 = [
    'key' => $someVariable,
    'other' => 'value',
    'key' => $someVariable, // Same value expression
];

// Negative cases - should NOT trigger errors

// Case 5: No duplicates
$array5 = [
    'key1' => 'value1',
    'key2' => 'value2',
    'key3' => 'value3',
];

// Case 6: Numeric keys (should be ignored)
$array6 = [
    0 => 'value1',
    1 => 'value2',
    0 => 'value3', // Numeric key, should be ignored
];

// Case 7: Variable keys (should be ignored)
$array7 = [
    $key => 'value1',
    'static' => 'value2',
    $key => 'value3', // Variable key, should be ignored
];
$key = 'dynamic';

// Case 8: Empty array
$array8 = [];

// Case 9: Single element
$array9 = ['key' => 'value'];