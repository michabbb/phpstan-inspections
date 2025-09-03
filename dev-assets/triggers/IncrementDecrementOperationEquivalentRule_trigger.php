<?php

// Positive cases - should trigger the rule

$var = 5;

// Self-assignment increment
$var += 1; // Should suggest: ++$var

// Self-assignment decrement
$var -= 1; // Should suggest: --$var

// Regular assignment increment (variable on left)
$var = $var + 1; // Should suggest: ++$var

// Regular assignment decrement (variable on left)
$var = $var - 1; // Should suggest: --$var

// Regular assignment increment (variable on right)
$var = 1 + $var; // Should suggest: ++$var

// Regular assignment decrement (variable on right)
$var = 1 - $var; // This won't trigger because it's not equivalent to decrement

// Negative cases - should NOT trigger the rule

$other = 10;

// Different value (not 1)
$var += 2; // Should NOT trigger

// Different variable
$var = $other + 1; // Should NOT trigger

// String manipulation (should avoid)
$str = "hello";
$str[0] += 1; // Should NOT trigger (string manipulation)

// Array operations (should work normally)
$arr = [1, 2, 3];
$arr[0] += 1; // Should trigger: ++$arr[0]

// Complex expressions
$var = $var + 1 + 2; // Should NOT trigger (not simple +1)