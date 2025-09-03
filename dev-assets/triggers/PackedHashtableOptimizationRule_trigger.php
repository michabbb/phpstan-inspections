<?php
declare(strict_types=1);

// Positive: not in natural ascending order -> should suggest reordering
$a = [2 => 'a', 1 => 'b', 3 => 'c'];

// Positive: numeric string keys in ascending order -> should suggest integer keys
$b = ['1' => 'x', '2' => 'y', '3' => 'z'];

// Negative: less than 3 elements -> no report
$c = [1 => 'a', 2 => 'b'];

// Negative: non-numeric string keys -> no report
$d = ['foo' => 1, 'bar' => 2, 'baz' => 3];

// Negative: leading zero numeric string keys -> no report
$e = ['01' => 'a', '02' => 'b', '03' => 'c'];

