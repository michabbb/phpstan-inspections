<?php
declare(strict_types=1);

// Test cases for AlterInForeachInspectorRule

// Case 1: Missing unset after foreach with reference
$array = [1, 2, 3, 4, 5];
foreach ($array as &$value) {
    $value *= 2;
}
// Missing unset($value) here - should trigger IDENTIFIER_MISSING_UNSET

echo "Array after modification: " . implode(', ', $array) . "\n";

// Case 2: Unnecessary unset for non-reference foreach
$names = ['Alice', 'Bob', 'Charlie'];
foreach ($names as $name) {
    echo "Name: " . $name . "\n";
}
unset($name); // Should trigger IDENTIFIER_UNNECESSARY_UNSET

// Case 3: Proper unset after reference foreach
$numbers = [10, 20, 30];
foreach ($numbers as &$num) {
    $num += 5;
}
unset($num); // This is correct - no error

// Case 4: Array assignment that could benefit from reference
$data = ['a' => 1, 'b' => 2, 'c' => 3];
foreach ($data as $key => $value) {
    $data[$key] = $value * 3; // Should suggest using reference - IDENTIFIER_SUGGEST_REFERENCE
}

// Case 5: No unset needed before return (control flow end)
function processItems($items) {
    foreach ($items as &$item) {
        $item = strtoupper($item);
    }
    return $items; // No unset needed before return
}

// Case 6: No unset needed before throw (control flow end)
function validateItems($items) {
    foreach ($items as &$item) {
        $item = trim($item);
    }
    throw new Exception("Validation failed"); // No unset needed before throw
}

// Case 7: Complex nested scenario
$matrix = [[1, 2], [3, 4], [5, 6]];
foreach ($matrix as &$row) {
    foreach ($row as &$cell) {
        $cell *= 2;
    }
    unset($cell); // Correct unset for inner loop
}
// Missing unset($row) here - should trigger error