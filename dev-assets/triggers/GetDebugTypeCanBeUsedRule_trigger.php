<?php

declare(strict_types=1);

// Positive case - should trigger the rule
$var = new stdClass();
$result = is_object($var) ? get_class($var) : gettype($var);

// Another positive case with different variable
$anotherVar = 'string';
$anotherResult = is_object($anotherVar) ? get_class($anotherVar) : gettype($anotherVar);

// Test with function parameter
function test($param) {
    return is_object($param) ? get_class($param) : gettype($param);
}

// Negative case - different condition
$negative1 = is_string($var) ? get_class($var) : gettype($var);

// Negative case - different true branch
$negative2 = is_object($var) ? 'object' : gettype($var);

// Negative case - different false branch
$negative3 = is_object($var) ? get_class($var) : 'string';

// Valid usage - no replacement needed
$valid1 = get_debug_type($var);
$valid2 = get_class($var);
$valid3 = gettype($var);