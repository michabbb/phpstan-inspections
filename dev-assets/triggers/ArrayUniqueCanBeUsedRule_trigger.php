<?php
declare(strict_types=1);

// Test cases for ArrayUniqueCanBeUsedRule

$numbers = [1, 2, 2, 3, 3, 3, 4, 5, 5];
$strings = ['apple', 'banana', 'apple', 'cherry', 'banana', 'date'];
$mixed = [1, '1', 2, '2', 1, 2];

// Case 1: array_keys(array_count_values($array)) pattern (should trigger error)
$uniqueNumbers = array_keys(array_count_values($numbers));
echo "Unique numbers: " . implode(', ', $uniqueNumbers) . "\n";

// Case 2: array_keys(array_count_values($array)) with string array (should trigger error)
$uniqueStrings = array_keys(array_count_values($strings));
echo "Unique strings: " . implode(', ', $uniqueStrings) . "\n";

// Case 3: count(array_count_values($array)) pattern (should trigger error)
$uniqueNumberCount = count(array_count_values($numbers));
echo "Count of unique numbers: " . $uniqueNumberCount . "\n";

// Case 4: count(array_count_values($array)) with string array (should trigger error)
$uniqueStringCount = count(array_count_values($strings));
echo "Count of unique strings: " . $uniqueStringCount . "\n";

// Case 5: Mixed type array with array_keys(array_count_values()) (should trigger error)
$uniqueMixed = array_keys(array_count_values($mixed));
echo "Unique mixed values: " . implode(', ', $uniqueMixed) . "\n";

// Case 6: Mixed type array with count(array_count_values()) (should trigger error)
$uniqueMixedCount = count(array_count_values($mixed));
echo "Count of unique mixed values: " . $uniqueMixedCount . "\n";

// Case 7: Nested in function call (should trigger error)
$result = max(array_keys(array_count_values($numbers)));
echo "Max unique value: " . $result . "\n";

// Case 8: Used in conditional (should trigger error)
if (count(array_count_values($numbers)) > 3) {
    echo "More than 3 unique numbers\n";
}

// Case 9: Used in assignment with complex expression (should trigger error)
$totalUniqueElements = count(array_count_values($strings)) + count(array_count_values($numbers));
echo "Total unique elements: " . $totalUniqueElements . "\n";

// Case 10: Multiple patterns in same block (should trigger multiple errors)
$data = [1, 1, 2, 3, 3, 4, 4, 4, 5];
$uniqueKeys = array_keys(array_count_values($data));
$uniqueCount = count(array_count_values($data));
echo "Keys: " . implode(', ', $uniqueKeys) . ", Count: " . $uniqueCount . "\n";

// Case 11: Proper usage - should NOT trigger errors
$properUnique = array_unique($numbers);
echo "Proper array_unique usage: " . implode(', ', $properUnique) . "\n";

// Case 12: Different function - should NOT trigger
$countValues = array_count_values($numbers);
echo "Count values result: " . print_r($countValues, true) . "\n";

// Case 13: array_keys without array_count_values - should NOT trigger
$simpleArray = ['a' => 1, 'b' => 2, 'c' => 3];
$keys = array_keys($simpleArray);
echo "Simple array keys: " . implode(', ', $keys) . "\n";

// Case 14: count without array_count_values - should NOT trigger
$simpleCount = count($numbers);
echo "Simple count: " . $simpleCount . "\n";

// Case 15: array_count_values used for actual counting - should NOT trigger
foreach (array_count_values($strings) as $value => $count) {
    echo "Value '$value' appears $count times\n";
}

// Case 16: Complex variable expressions (should trigger if properly extracted)
$dataArray = ['x', 'y', 'x', 'z', 'y'];
$uniqueFromComplex = array_keys(array_count_values($dataArray));
$countFromComplex = count(array_count_values($dataArray));

// Case 17: Function parameter (should trigger error)
function processUniqueCount($arr) {
    return count(array_count_values($arr));
}

$result = processUniqueCount($numbers);
echo "Function result: " . $result . "\n";

// Case 18: Return statement (should trigger error)
function getUniqueValues($arr) {
    return array_keys(array_count_values($arr));
}

$unique = getUniqueValues($strings);
echo "Returned unique values: " . implode(', ', $unique) . "\n";