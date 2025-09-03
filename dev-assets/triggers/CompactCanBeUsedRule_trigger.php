<?php

declare(strict_types=1);

// Positive cases - should trigger the rule

$foo = 'value1';
$bar = 'value2';
$baz = 'value3';

// This should trigger: compact can be used
$array1 = ['foo' => $foo, 'bar' => $bar];

// This should trigger: compact can be used
$array2 = ['foo' => $foo, 'bar' => $bar, 'baz' => $baz];

// This should trigger: compact can be used (different order)
$array3 = ['bar' => $bar, 'foo' => $foo];

// Negative cases - should NOT trigger the rule

// Single element - not enough for compact
$single = ['foo' => $foo];

// Key doesn't match variable name
$array4 = ['foo' => $bar]; // key 'foo' but value is $bar

// Value is not a variable
$array5 = ['foo' => 'literal'];

// Key is not a string literal
$key = 'foo';
$array6 = [$key => $foo];

// Mixed valid and invalid elements
$array7 = ['foo' => $foo, 'bar' => 'literal'];

// No keys (indexed array)
$array8 = [$foo, $bar];

// Empty array
$empty = [];

// Used in assignment context (this might be allowed or not depending on implementation)
$assigned = ['foo' => $foo, 'bar' => $bar];