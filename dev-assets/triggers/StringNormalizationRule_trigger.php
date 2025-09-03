<?php declare(strict_types=1);

// Positive cases - should trigger StringNormalizationRule

// Inverted nesting: length manipulation on case manipulation
$normalizedString = trim(strtolower($string)); // Should suggest strtolower(trim($string))
$normalizedString = ltrim(strtoupper($string)); // Should suggest strtoupper(ltrim($string))
$normalizedString = rtrim(ucfirst($string)); // Should suggest ucfirst(rtrim($string))
$normalizedString = substr(strtolower($string), 0, 10); // Should suggest strtolower(substr($string, 0, 10))

// Senseless nesting: same case manipulation function twice
$normalizedString = strtolower(strtolower($string)); // Should report senseless nesting
$normalizedString = strtoupper(strtoupper($string)); // Should report senseless nesting

// Mixed senseless nesting
$normalizedString = ucfirst(strtolower($string)); // Should report senseless nesting for strtolower
$normalizedString = lcfirst(strtoupper($string)); // Should report senseless nesting for strtoupper

// Negative cases - should NOT trigger StringNormalizationRule

// Correct order: case manipulation on length manipulation
$normalizedString = strtolower(trim($string)); // Correct order
$normalizedString = strtoupper(ltrim($string)); // Correct order

// Different functions - no issue
$normalizedString = strlen(strtolower($string)); // Different function types
$normalizedString = explode(',', strtolower($string)); // Different function types

// Single function calls - no nesting
$normalizedString = trim($string); // Single function
$normalizedString = strtolower($string); // Single function

// Non-string functions
$result = array_map('strtolower', $array); // Not a direct function call nesting