<?php

// Positive cases - should trigger the rule

$haystack = "Hello World";
$needle = "Hello";
$other = "other";

// This should trigger: substr used as strpos
if (substr($haystack, 0, strlen($needle)) === $needle) {
    echo "Starts with Hello\n";
}

// This should trigger: mb_substr used as strpos
if (mb_substr($haystack, 0, mb_strlen($needle)) === $needle) {
    echo "Starts with Hello (mb)\n";
}

// This should trigger: case-insensitive version
if (strtolower(substr($haystack, 0, strlen($needle))) === strtolower($needle)) {
    echo "Starts with hello (case insensitive)\n";
}

// This should trigger: mb version with encoding
if (mb_substr($haystack, 0, mb_strlen($needle), 'UTF-8') === $needle) {
    echo "Starts with Hello (mb with encoding)\n";
}

// Negative cases - should NOT trigger the rule

// Different starting position
if (substr($haystack, 1, strlen($needle)) === $needle) {
    echo "Not starting with Hello\n";
}

// Not a comparison
$result = substr($haystack, 0, strlen($needle));

// Different function
if (strpos($haystack, $needle) === 0) {
    echo "Already using strpos\n";
}

// Not using strlen in third parameter
if (substr($haystack, 0, 5) === $needle) {
    echo "Fixed length\n";
}

// Comparison with different variable
if (substr($haystack, 0, strlen($needle)) === $other) {
    echo "Different comparison\n";
}