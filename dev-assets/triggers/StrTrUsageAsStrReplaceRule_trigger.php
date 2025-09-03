<?php

// Positive cases - should trigger the rule

// Single character replacement
$normalizedPath = strtr($string, '\\', '/');

// Quote replacement
$fixedText = strtr($text, '"', "'");

// Escaped character
$cleanData = strtr($data, '\n', ' ');

// Another single char
$result = strtr($input, 'a', 'b');

// Two character string (should trigger)
$replaced = strtr($content, 'ab', 'cd');

// Escaped quote in single quotes
$escaped = strtr($value, "'", '"');

// Escaped backslash
$pathFixed = strtr($windowsPath, '\\', '/');

// Double quoted with escape
$jsonFixed = strtr($jsonString, '"', "'");

// Negative cases - should NOT trigger the rule

// More than 2 characters - should not trigger
$longReplace = strtr($text, 'long', 'short');

// Empty string - should not trigger
$emptyReplace = strtr($data, '', ' ');

// Variables instead of literals - should not trigger
$dynamicReplace = strtr($subject, $search, $replace);

// Function call - should not trigger
$funcReplace = strtr($string, getSearch(), getReplace());

// Only 2 arguments - should not trigger
$twoArgs = strtr($string, 'a');

// More than 3 arguments - should not trigger (though this would be syntax error)
$fourArgs = strtr($string, 'a', 'b', 'extra');

// Different function - should not trigger
$otherFunc = str_replace($string, 'a', 'b');