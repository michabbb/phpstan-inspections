<?php
declare(strict_types=1);

// Test cases for ArraySearchUsedAsInArrayRule

$items = ['apple', 'banana', 'cherry', 'date'];
$numbers = [1, 2, 3, 4, 5];
$value = 'banana';
$number = 3;
$notFound = 'grape';

// Case 1: array_search compared with false using === (should trigger error)
if (array_search($value, $items) === false) {
    echo "Value not found (comparison with false)\n";
}

// Case 2: array_search compared with false using !== (should trigger error)
if (array_search($value, $items) !== false) {
    echo "Value found (comparison with false)\n";
}

// Case 3: false compared with array_search using === (should trigger error)
if (false === array_search($notFound, $items)) {
    echo "Not found (false on left side)\n";
}

// Case 4: false compared with array_search using !== (should trigger error)
if (false !== array_search($value, $items)) {
    echo "Found (false on left side)\n";
}

// Case 5: array_search compared with true using === (should trigger error - makes no sense)
if (array_search($value, $items) === true) {
    echo "This makes no sense - array_search never returns true\n";
}

// Case 6: array_search compared with true using !== (should trigger error - makes no sense)
if (array_search($value, $items) !== true) {
    echo "This also makes no sense - array_search never returns true\n";
}

// Case 7: true compared with array_search using === (should trigger error)
if (true === array_search($value, $items)) {
    echo "True on left side - still makes no sense\n";
}

// Case 8: true compared with array_search using !== (should trigger error)
if (true !== array_search($value, $items)) {
    echo "True on left side with !== - still makes no sense\n";
}

// Case 9: Multiple array_search comparisons with false
$result1 = array_search($value, $items) === false;
$result2 = array_search($number, $numbers) !== false;

// Case 10: Complex expression with array_search and false comparison
if ((array_search($value, $items) === false) || empty($items)) {
    echo "Complex expression with false comparison\n";
}

// Case 11: Proper usage - should NOT trigger errors
$position = array_search($value, $items);
if (is_int($position)) {
    echo "Proper check for integer position\n";
}

// Case 12: Comparison with other values - should NOT trigger
if (array_search($value, $items) === 0) {
    echo "Comparison with 0 - should not trigger\n";
}

// Case 13: Comparison with string - should NOT trigger
if (array_search($value, $items) === 'some_string') {
    echo "Comparison with string - should not trigger\n";
}

// Case 14: Using == instead of === - should NOT trigger (not identical comparison)
if (array_search($value, $items) == false) {
    echo "Using == instead of === - should not trigger\n";
}

// Case 15: array_search with strict parameter but still compared with false
if (array_search($value, $items, true) === false) {
    echo "With strict parameter but still compared with false - should trigger\n";
}

// Case 16: Nested comparisons
if (($result = array_search($value, $items)) === false) {
    echo "Assignment within comparison - should trigger\n";
}

// Case 17: Multiple conditions
if (array_search($value, $items) === false && array_search($number, $numbers) !== false) {
    echo "Multiple array_search comparisons - should trigger for both\n";
}