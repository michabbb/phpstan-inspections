<?php
declare(strict_types=1);

// Positive cases - should trigger the rule (patterns without alphabetic characters)

$haystack = 'hello123world';
$pattern1 = '123';  // No alphabetic characters - should suggest strstr instead of stristr
$pattern2 = '456';  // No alphabetic characters - should suggest strpos instead of stripos
$pattern3 = '789';  // No alphabetic characters - should suggest strrpos instead of strripos

// These should trigger the rule
$result1 = stristr($haystack, $pattern1);
$result2 = stripos($haystack, $pattern2);
$result3 = strripos($haystack, $pattern3);

// More examples with different patterns
$result4 = stristr('test_data_123', '_');      // Underscore only
$result5 = stripos('file-name.ext', '-');      // Hyphen only
$result6 = strripos('user@domain.com', '@');   // Special character only

// Negative cases - should NOT trigger the rule

// Patterns with alphabetic characters
$result7 = stristr($haystack, 'world');        // Contains letters
$result8 = stripos($haystack, 'hello');        // Contains letters
$result9 = strripos($haystack, 'test');        // Contains letters

// Empty pattern
$result10 = stristr($haystack, '');            // Empty string

// Non-string literal patterns (variables)
$patternVar = '123';
$result11 = stristr($haystack, $patternVar);   // Variable, not string literal

// Different functions (not in the mapping)
$result12 = strpos($haystack, $pattern1);      // Already case-sensitive
$result13 = strstr($haystack, $pattern1);      // Already case-sensitive

// Wrong number of arguments
$result14 = stristr($haystack);                // Missing pattern argument