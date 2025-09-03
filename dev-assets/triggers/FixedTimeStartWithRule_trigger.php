<?php

// Positive cases - should trigger the rule

$haystack = "Hello World";
$needle = "Hello";

// This should trigger: strpos with === 0 (variable)
if (strpos($haystack, $needle) === 0) {
    echo "Starts with Hello\n";
}

// This should trigger: strpos with string literal === 0
if (strpos($haystack, "Hello") === 0) {
    echo "Starts with Hello (literal)\n";
}

// This should trigger: stripos with !== 0 (variable)
if (stripos($haystack, $needle) !== 0) {
    echo "Does not start with Hello\n";
}

// This should trigger: stripos with string literal !== 0
if (stripos($haystack, "Hello") !== 0) {
    echo "Does not start with Hello (literal)\n";
}

// This should trigger: strpos with == 0 (loose comparison)
if (strpos($haystack, $needle) == 0) {
    echo "Starts with Hello (loose)\n";
}

// This should trigger: stripos with != 0 (loose comparison)
if (stripos($haystack, $needle) != 0) {
    echo "Does not start with Hello (loose)\n";
}

// Negative cases - should NOT trigger the rule

// Different function
if (strstr($haystack, $needle) === false) {
    echo "Does not contain Hello\n";
}

// Different comparison value
if (strpos($haystack, $needle) === 1) {
    echo "Starts at position 1\n";
}

// Not a string literal as second argument
$dynamicNeedle = "Hello";
if (strpos($haystack, $dynamicNeedle) === 0) {
    echo "Starts with dynamic needle\n";
}

// Not in a comparison
$result = strpos($haystack, $needle);

// Different comparison operator
if (strpos($haystack, $needle) > 0) {
    echo "Does not start with Hello\n";
}

// Wrong number of arguments
if (strpos($haystack) === 0) {
    echo "Invalid call\n";
}