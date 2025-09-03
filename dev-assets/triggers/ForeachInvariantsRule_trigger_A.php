<?php
declare(strict_types=1);

// This file contains examples that should NOT trigger the ForeachInvariantsRule

// Example 1: For-loop that doesn't start at 0
$items = ['a', 'b', 'c'];
for ($i = 1; $i < count($items); $i++) {
    echo $items[$i] . "\n";
}

// Example 2: For-loop with different increment pattern
$numbers = [1, 2, 3, 4, 5];
for ($i = 0; $i < count($numbers); $i += 2) {
    echo $numbers[$i] . "\n";
}

// Example 3: For-loop without array access
$items = ['a', 'b', 'c'];
for ($i = 0; $i < count($items); $i++) {
    echo "Item " . $i . "\n"; // Only using index, not array access
}

// Example 4: For-loop with different array in condition vs body
$items = ['a', 'b', 'c'];
$otherArray = [1, 2, 3];
for ($i = 0; $i < count($items); $i++) {
    echo $otherArray[$i] . "\n"; // Different array in body
}

// Example 5: For-loop with multiple conditions
$items = ['a', 'b', 'c'];
for ($i = 0; $i < count($items) && $i < 10; $i++) {
    echo $items[$i] . "\n";
}

// Example 6: For-loop with multiple increment expressions
$items = ['a', 'b', 'c'];
for ($i = 0; $i < count($items); $i++, $j++) {
    echo $items[$i] . "\n";
}

// Example 7: Already using foreach (should not trigger)
$items = ['a', 'b', 'c'];
foreach ($items as $item) {
    echo $item . "\n";
}

// Example 8: For-loop with non-count condition
$items = ['a', 'b', 'c'];
for ($i = 0; $i < 5; $i++) { // Fixed number instead of count()
    echo $items[$i] . "\n";
}