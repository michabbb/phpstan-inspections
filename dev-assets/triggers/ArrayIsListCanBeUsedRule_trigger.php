<?php declare(strict_types=1);

// Trigger file for ArrayIsListCanBeUsedRule
// This file demonstrates positive and negative cases for the rule

// Positive cases - should trigger the rule

// Pattern 1: array_values($array) === $array
$array1 = [1, 2, 3];
if (array_values($array1) === $array1) { // Should trigger: array_is_list($array1)
    echo "Pattern 1a triggered\n";
}

if ($array1 === array_values($array1)) { // Should trigger: array_is_list($array1)
    echo "Pattern 1b triggered\n";
}

// Pattern 1 with inequality
if (array_values($array1) !== $array1) { // Should trigger: !array_is_list($array1)
    echo "Pattern 1c triggered\n";
}

// Pattern 2: array_keys($array) === range(0, count($array) - 1)
$array2 = ['a', 'b', 'c'];
if (array_keys($array2) === range(0, count($array2) - 1)) { // Should trigger: array_is_list($array2)
    echo "Pattern 2a triggered\n";
}

if (range(0, count($array2) - 1) === array_keys($array2)) { // Should trigger: array_is_list($array2)
    echo "Pattern 2b triggered\n";
}

// Pattern 2 with inequality
if (array_keys($array2) != range(0, count($array2) - 1)) { // Should trigger: !array_is_list($array2)
    echo "Pattern 2c triggered\n";
}

// Test with different variable
$myData = [10, 20, 30];
if (array_values($myData) == $myData) { // Should trigger: array_is_list($myData)
    echo "Different variable triggered\n";
}

// Negative cases - should NOT trigger the rule

// Different array arguments
$arr1 = [1, 2];
$arr2 = [3, 4];
if (array_values($arr1) === $arr2) { // Should NOT trigger - different arrays
    echo "No trigger 1\n";
}

// Wrong function
if (array_flip($array1) === $array1) { // Should NOT trigger - not array_values
    echo "No trigger 2\n";
}

// Wrong comparison with array_keys
if (array_keys($array1) === range(1, count($array1))) { // Should NOT trigger - range doesn't start with 0
    echo "No trigger 3\n";
}

// Wrong range end
if (array_keys($array1) === range(0, count($array1))) { // Should NOT trigger - range should be count - 1
    echo "No trigger 4\n";
}

// Different array in count
if (array_keys($array1) === range(0, count($array2) - 1)) { // Should NOT trigger - different arrays
    echo "No trigger 5\n";
}

// Array with string keys (associative) - would still match pattern but semantically different
$assocArray = ['key1' => 'value1', 'key2' => 'value2'];
if (array_values($assocArray) === $assocArray) { // Should trigger - but logically this would be false
    echo "Assoc array pattern\n";
}

echo "Test file executed successfully\n";