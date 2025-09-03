<?php

declare(strict_types=1);

// Test cascading replacements
$var = str_replace('a', 'b', $input);
$var = str_replace('c', 'd', $var); // Should trigger cascading error

// Test nested replacements
$result = str_replace('x', 'y', str_replace('a', 'b', $input)); // Should trigger nesting error

// Test search simplification
$simplified = str_replace(['a', 'a', 'a'], 'b', $input); // Should trigger simplification error

// Valid cases that should not trigger
$valid1 = str_replace('a', 'b', $input);
$valid2 = str_replace(['a', 'b'], 'c', $input);
$valid3 = str_replace(['a', 'a'], ['b', 'c'], $input);