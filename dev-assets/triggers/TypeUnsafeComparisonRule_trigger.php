<?php declare(strict_types=1);

// Test file for TypeUnsafeComparisonRule
// This file contains examples that should trigger the rule

class TestClass {
    public function __construct() {}
}

// Positive cases - should trigger the rule

// String literal comparison with non-string operand
$value = 'test';
if ($value == 123) { // Should trigger: Safely use '===' here.
    echo "Equal";
}

if ($value != 123) { // Should trigger: Safely use '!==' here.
    echo "Not equal";
}

// String literal with object (should suggest __toString implementation)
$object = new TestClass();
if ($object == 'test') { // Should trigger: Class should implement __toString() method
    echo "Object equals string";
}

// Non-comparable object comparison
$date1 = new DateTime();
$date2 = new DateTime();
if ($date1 == $date2) { // Should NOT trigger (DateTime is comparable)
    echo "Dates equal";
}

$closure1 = function() {};
$closure2 = function() {};
if ($closure1 == $closure2) { // Should NOT trigger (Closure is comparable)
    echo "Closures equal";
}

// Non-comparable objects
$std1 = new stdClass();
$std2 = new stdClass();
if ($std1 == $std2) { // Should trigger: Please consider using more strict '===' here
    echo "Objects equal";
}

// Negative cases - should NOT trigger the rule

// Numeric string literals (should be safe)
if ($value == '123') { // Should NOT trigger (numeric string)
    echo "Numeric string";
}

// Empty string literals
if ($value == '') { // Should NOT trigger (empty string)
    echo "Empty string";
}

// Strict comparisons (already correct)
if ($value === 123) { // Should NOT trigger (already strict)
    echo "Strict equal";
}

if ($value !== 123) { // Should NOT trigger (already strict)
    echo "Strict not equal";
}