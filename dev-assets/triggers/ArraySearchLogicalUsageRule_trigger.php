<?php
declare(strict_types=1);

// Test cases for ArraySearchLogicalUsageRule

$items = ['apple', 'banana', 'cherry', 'date'];
$numbers = [1, 2, 3, 4, 5];
$value = 'banana';
$number = 3;

// Case 1: array_search in if condition (should trigger error)
if (array_search($value, $items)) {
    echo "Found item\n";
}

// Case 2: array_search in elseif condition (should trigger error)
if (false) {
    echo "Never reached\n";
} elseif (array_search($number, $numbers)) {
    echo "Found number\n";
}

// Case 3: array_search in while condition (should trigger error)
$counter = 0;
while (array_search($counter, $numbers) && $counter < 10) {
    $counter++;
}

// Case 4: array_search in ternary condition (should trigger error)
$result = array_search($value, $items) ? "found" : "not found";

// Case 5: array_search with boolean AND (should trigger error)
if (array_search($value, $items) && count($items) > 2) {
    echo "Item found and array has more than 2 elements\n";
}

// Case 6: array_search with boolean OR (should trigger error)
if (array_search($value, $items) || empty($items)) {
    echo "Item found or array is empty\n";
}

// Case 7: Negated array_search (should trigger error)
if (!array_search($value, $items)) {
    echo "Item not found\n";
}

// Case 8: Complex boolean expression with array_search (should trigger error)
if ((array_search($value, $items) && $value !== '') || count($items) === 0) {
    echo "Complex condition with array_search\n";
}

// Case 9: array_search in nested boolean expression (should trigger error)
if ($value !== '' && array_search($value, $items)) {
    echo "Value is not empty and was found\n";
}

// Case 10: Multiple array_search calls in same expression (should trigger multiple errors)
if (array_search($value, $items) || array_search($number, $numbers)) {
    echo "Either value or number was found\n";
}

// Case 11: Proper usage with strict comparison (should NOT trigger)
if (array_search($value, $items, true) !== false) {
    echo "Proper usage with strict comparison - no error\n";
}

// Case 12: array_search used in assignment (should NOT trigger - not in logical context)
$position = array_search($value, $items);

// Case 13: array_search with strict parameter but in logical context (should still trigger)
if (array_search($value, $items, true)) {
    echo "Even with strict parameter, still in logical context - should trigger\n";
}

// Case 14: Deeply nested logical expression (should trigger error)
if (true && (false || array_search($value, $items))) {
    echo "Deeply nested array_search - should trigger\n";
}

// Case 15: array_search in do-while (should trigger error)
do {
    echo "Do-while loop\n";
    break;
} while (array_search($value, $items));