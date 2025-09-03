<?php declare(strict_types=1);

$foo = 'bar';
$arr = ['bar' => 1, 'baz' => 2];
$emptyArr = [];

// --- Pattern 1: in_array($needle, array_keys($haystack)) ---

// Positive case: Should trigger 'array_key_exists' suggestion
$result1 = in_array($foo, array_keys($arr));

// Negative case: Correct usage of in_array with array_keys
$result2 = in_array(1, array_values($arr));

// Negative case: Correct usage of in_array without array_keys
$result3 = in_array('bar', $arr);

// --- Pattern 2: in_array($needle, [$singleValue]) or in_array($needle, []) ---

// Positive case: Single value array, non-strict
$result4 = in_array('bar', ['bar']);

// Positive case: Single value array, strict
$result5 = in_array('bar', ['bar'], true);

// Positive case: Single value array, non-strict, negated
$result6 = !in_array('bar', ['baz']);

// Positive case: Single value array, strict, negated
$result7 = !in_array('bar', ['baz'], true);

// Positive case: Empty array, non-strict
$result8 = in_array('bar', []);

// Positive case: Empty array, strict
$result9 = in_array('bar', [], true);

// Positive case: Empty array, non-strict, negated
$result10 = !in_array('bar', []);

// Positive case: Empty array, strict, negated
$result11 = !in_array('bar', [], true);

// Positive case: Single value array, non-strict, compared to true
$result12 = (in_array('bar', ['bar']) == true);

// Positive case: Single value array, strict, compared to false
$result13 = (in_array('bar', ['baz'], true) === false);

// Negative case: Multiple values in array
$result14 = in_array('bar', ['bar', 'baz']);

// Negative case: Variable as array
$result15 = in_array('bar', $emptyArr);

// Negative case: More than 3 arguments (invalid in_array call, but not this rule's concern)
// in_array('bar', ['bar'], true, 'extra');
