<?php

declare(strict_types=1);

// Positive case 1: Consecutive array assignments that should trigger the rule
$array = [1, 2, 3, 4];
$first = $array[0];
$second = $array[1]; // This should trigger: "Perhaps 'list(...) = $array' can be used instead"

// Positive case 2: More consecutive assignments
$data = ['a', 'b', 'c'];
$name = $data[0];
$value = $data[1]; // This should trigger
$extra = $data[2]; // This should trigger

// Positive case 3: Foreach with list() assignment that should trigger the rule
$items = [[1, 2], [3, 4], [5, 6]];
foreach ($items as $item) {
    list($a, $b) = $item; // This should trigger: "foreach (... as list(...)) could be used instead."
}

// Positive case 4: Another foreach case
$coordinates = [[10, 20], [30, 40]];
foreach ($coordinates as $coord) {
    list($x, $y) = $coord; // This should trigger
}