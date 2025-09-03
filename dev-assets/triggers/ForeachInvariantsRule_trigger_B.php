<?php
declare(strict_types=1);

// This file contains examples that SHOULD trigger the ForeachInvariantsRule

// Example 1: Basic for-loop that can be converted to foreach
$items = ['a', 'b', 'c'];
for ($i = 0; $i < count($items); $i++) {
    echo $items[$i] . "\n";
}

// Example 2: For-loop with array modification
$numbers = [1, 2, 3, 4, 5];
for ($i = 0; $i < count($numbers); $i++) {
    $numbers[$i] = $numbers[$i] * 2;
}

// Example 3: For-loop with key-value access pattern
$data = ['key1' => 'value1', 'key2' => 'value2'];
for ($i = 0; $i < count($data); $i++) {
    $keys = array_keys($data);
    $values = array_values($data);
    echo $keys[$i] . ': ' . $values[$i] . "\n";
}

// Example 4: Nested array access
$matrix = [[1, 2], [3, 4], [5, 6]];
for ($i = 0; $i < count($matrix); $i++) {
    for ($j = 0; $j < count($matrix[$i]); $j++) {
        echo $matrix[$i][$j] . ' ';
    }
    echo "\n";
}