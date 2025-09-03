<?php

// Negative test cases - these should NOT trigger the DisconnectedForeachInstructionRule

$items = [1, 2, 3];
$result = [];

// Case 1: Assignment using loop variable - should NOT trigger
foreach ($items as $item) {
    $result[] = $item * 2; // OK - uses loop variable
}

// Case 2: Increment/decrement - should NOT trigger
foreach ($items as $item) {
    $counter++; // OK - assignment
    $result[] = $item;
}

// Case 3: Object creation - should NOT trigger
foreach ($items as $item) {
    $obj = new stdClass(); // OK - new expression
    $result[] = $item;
}

// Case 4: Clone operation - should NOT trigger
foreach ($items as $item) {
    $cloned = clone $item; // OK - clone expression (if $item is object)
    $result[] = $item;
}

// Case 5: Control statements - should NOT trigger
foreach ($items as $item) {
    if ($item > 2) {
        break; // OK - control statement
    }
    $result[] = $item;
}

// Case 6: Return statement - should NOT trigger
function processItems(array $items): array {
    $result = [];
    foreach ($items as $item) {
        if ($item === null) {
            return $result; // OK - control statement
        }
        $result[] = $item;
    }
    return $result;
}

// Case 7: Array accumulation - should NOT trigger
foreach ($items as $key => $value) {
    $result[$key] = $value; // OK - array assignment
}

// Case 8: Using loop variable in condition - should NOT trigger
foreach ($items as $item) {
    if ($item > 0) { // OK - uses loop variable
        $result[] = $item;
    }
}

// Case 9: Nested loop with outer variable usage - should NOT trigger
$outerVar = 'test';
foreach ($items as $item) {
    foreach ([1, 2] as $inner) {
        echo $outerVar; // OK - uses variable from outer scope
        $result[] = $item + $inner;
    }
}

// Case 10: Variable modified in loop - should NOT trigger
foreach ($items as $item) {
    $accumulator = 0; // This creates a dependency
    $accumulator += $item;
    $result[] = $accumulator;
}