<?php
declare(strict_types=1);

// Define variables to avoid undefined variable errors
$haystack = "Hello World";
$needle = "world";
$needleVar = "test";

// Positive cases - should trigger the rule

// Case 1: strpos with strtolower on first argument
$result1 = strpos(strtolower($haystack), $needle);

// Case 2: strpos with strtolower on second argument
$result2 = strpos($haystack, strtolower($needle));

// Case 3: strpos with strtoupper on both arguments
$result3 = strpos(strtoupper($haystack), strtoupper($needle));

// Case 4: mb_strpos with mb_strtolower
$result4 = mb_strpos(mb_strtolower($haystack), $needle);

// Case 5: strrpos with strtolower
$result5 = strrpos(strtolower($haystack), $needle);

// Case 6: mb_strrpos with mb_strtoupper
$result6 = mb_strrpos($haystack, mb_strtoupper($needle));

// Negative cases - should NOT trigger the rule

// Case 7: Direct strpos call without case manipulation
$result7 = strpos($haystack, $needle);

// Case 8: stripos (already case-insensitive)
$result8 = stripos($haystack, $needle);

// Case 9: strpos with other function calls
$result9 = strpos(substr($haystack, 0, 10), $needle);

// Case 10: strpos with variables (not function calls)
$result10 = strpos($haystack, $needleVar);

// Case 11: Different function entirely
$result11 = strlen($haystack);