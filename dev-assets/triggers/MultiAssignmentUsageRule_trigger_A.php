<?php

declare(strict_types=1);

// Negative case 1: Non-consecutive indices (should not trigger)
$array = [1, 2, 3, 4];
$first = $array[0];
$third = $array[2]; // Non-consecutive, should not trigger

// Negative case 2: Different variables (should not trigger)
$data1 = [1, 2];
$data2 = [3, 4];
$value1 = $data1[0];
$value2 = $data2[0]; // Different variables, should not trigger

// Negative case 3: Non-numeric indices (should not trigger)
$array = ['a' => 1, 'b' => 2];
$first = $array['a'];
$second = $array['b']; // String indices, should not trigger

// Negative case 4: Already using list() properly (should not trigger)
$array = [1, 2, 3];
list($first, $second) = $array; // Already using list(), should not trigger

// Negative case 5: Foreach already using list() in declaration (should not trigger)
$items = [[1, 2], [3, 4]];
foreach ($items as list($a, $b)) {
    // Already using list() in foreach declaration, should not trigger
    echo $a + $b;
}

// Negative case 6: Foreach with different variable (should not trigger)
$items = [[1, 2], [3, 4]];
foreach ($items as $item) {
    $otherVar = [5, 6];
    list($x, $y) = $otherVar; // Different variable, should not trigger
}

// Negative case 7: Single assignment (should not trigger)
$array = [1, 2];
$first = $array[0]; // Single assignment, should not trigger