<?php

declare(strict_types=1);

// Trigger file for ImplodeArgumentsOrderRule
// This file demonstrates violations and correct usage of the implode() function

// VIOLATION: String literal as second argument suggests wrong order
$array1 = ['foo', 'bar', 'baz'];
$result1 = implode($array1, ','); // Should trigger the rule

// VIOLATION: Another case with string literal as second argument
$array2 = ['one', 'two', 'three'];
$result2 = implode($array2, ' | '); // Should trigger the rule

// CORRECT: String literal as first argument (correct order)
$array3 = ['a', 'b', 'c'];
$result3 = implode(',', $array3); // Should NOT trigger

// CORRECT: Variable as second argument (doesn't trigger since it's not a string literal)
$glue = '-';
$array4 = ['x', 'y', 'z'];
$result4 = implode($array4, $glue); // Should NOT trigger (not a string literal)

// EDGE CASE: Single argument
$array5 = ['single'];
$result5 = implode($array5); // Should NOT trigger (only one argument)

// EDGE CASE: Three arguments
$result6 = implode(',', $array3, 'extra'); // Should NOT trigger (not exactly 2 arguments)