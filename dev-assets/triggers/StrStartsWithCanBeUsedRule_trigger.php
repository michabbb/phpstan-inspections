<?php

// This file demonstrates violations of the StrStartsWithCanBeUsedRule

$haystack = "Hello World";
$needle = "Hello";

// Violation: strpos === 0 should be str_starts_with
if (strpos($haystack, $needle) === 0) {
    echo "Starts with Hello\n";
}

// Violation: strpos !== 0 should be !str_starts_with
if (strpos($haystack, $needle) !== 0) {
    echo "Does not start with Hello\n";
}

// Violation: mb_strpos === 0 should be str_starts_with
if (mb_strpos($haystack, $needle) === 0) {
    echo "Starts with Hello (mb)\n";
}

// Violation: mb_strpos !== 0 should be !str_starts_with
if (mb_strpos($haystack, $needle) !== 0) {
    echo "Does not start with Hello (mb)\n";
}

// No violation: different comparison value
if (strpos($haystack, $needle) === 1) {
    echo "Position 1\n";
}

// No violation: not a comparison with 0
$pos = strpos($haystack, $needle);
if ($pos !== false) {
    echo "Found at position: " . $pos . "\n";
}