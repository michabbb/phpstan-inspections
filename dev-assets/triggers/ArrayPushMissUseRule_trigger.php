<?php

declare(strict_types=1);

// Valid usage: array_push with multiple elements (should NOT trigger)
$array1 = [];
array_push($array1, 'item1', 'item2', 'item3');

// Invalid usage: array_push with single element (should trigger)
$array2 = [];
array_push($array2, 'single_item');

// Another invalid usage
$array3 = [1, 2, 3];
array_push($array3, 4);

// Valid alternative: using array assignment (should NOT trigger)
$array4 = [];
$array4[] = 'item';

// Valid usage: array_push with more than 2 args (should NOT trigger)
$array5 = [];
array_push($array5, 'a', 'b');

// Edge case: array_push with 1 argument (should NOT trigger, but unusual)
$array6 = [];
// array_push($array6); // This would be invalid PHP syntax

// Valid usage: different array functions (should NOT trigger)
$array7 = [];
array_unshift($array7, 'first');
array_pop($array7);
array_shift($array7);