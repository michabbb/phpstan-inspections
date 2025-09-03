<?php

declare(strict_types=1);

// Test file for SubStrShortHandUsageRule
// This file contains substr() and mb_substr() calls that should trigger the rule

$testString = "Hello World";
$anotherString = "Test String";

// VIOLATION: substr($var, 0) - redundant substring from start
$result1 = substr($testString, 0);

// VIOLATION: mb_substr($var, 0) - redundant substring from start
$result2 = mb_substr($anotherString, 0);

// VIOLATION: substr($var, 0, strlen($var)) - substring of entire string
$result3 = substr($testString, 0, strlen($testString));

// VIOLATION: mb_substr($var, 0, mb_strlen($var)) - substring of entire string
$result4 = mb_substr($anotherString, 0, mb_strlen($anotherString));

// These should NOT trigger the rule (different offset or different variable)
$valid1 = substr($testString, 1); // offset is not 0
$valid2 = substr($testString, 0, 5); // length is not strlen($testString)
$valid3 = substr($testString, 0, strlen($anotherString)); // different variable in strlen

// Edge cases that should not trigger
$dynamicOffset = 1;
$valid4 = substr($testString, $dynamicOffset); // dynamic offset, not literal 0

echo "Test completed\n";