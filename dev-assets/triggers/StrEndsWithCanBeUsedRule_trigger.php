<?php

declare(strict_types=1);

// Test file for StrEndsWithCanBeUsedRule
// This file contains substr() and mb_substr() calls that should trigger the rule

$testString = "Hello World";
$suffix = "World";
$mbSuffix = "世界";

// VIOLATION: substr($str, -strlen($suffix)) === $suffix should be str_ends_with($str, $suffix)
$result1 = substr($testString, -strlen($suffix)) === $suffix;

// VIOLATION: mb_substr($str, -mb_strlen($suffix)) === $suffix should be str_ends_with($str, $suffix)
$result2 = mb_substr($testString, -mb_strlen($mbSuffix)) === $mbSuffix;

// VIOLATION: substr($str, -strlen($suffix)) !== $suffix should be !str_ends_with($str, $suffix)
$result3 = substr($testString, -strlen($suffix)) !== $suffix;

// VIOLATION: mb_substr($str, -mb_strlen($suffix)) != $suffix should be !str_ends_with($str, $suffix)
$result4 = mb_substr($testString, -mb_strlen($mbSuffix)) != $mbSuffix;

// VIOLATION: substr($str, -strlen($suffix)) == $suffix should be str_ends_with($str, $suffix)
$result5 = substr($testString, -strlen($suffix)) == $suffix;

// VIOLATION: mb_substr($str, -mb_strlen($suffix)) !== $mbSuffix should be !str_ends_with($str, $suffix)
$result6 = mb_substr($testString, -mb_strlen($mbSuffix)) !== $mbSuffix;

// These should NOT trigger the rule (different patterns)

// Not a comparison
$notComparison = substr($testString, -strlen($suffix));

// Different offset (not negative strlen)
$differentOffset = substr($testString, -5) === $suffix;

// Not using strlen/mb_strlen in the negative offset
$notStrlen = substr($testString, -4) === $suffix;

// Comparison with different variable
$differentVar = substr($testString, -strlen($suffix)) === "different";

// Not a substr call
$notSubstr = strpos($testString, $suffix) === (strlen($testString) - strlen($suffix));

// Valid use of substr (positive offset)
$validSubstr = substr($testString, 0, 5);

// Edge case: variable in strlen
$suffixVar = "World";
$result7 = substr($testString, -strlen($suffixVar)) === $suffixVar;

echo "Test completed\n";