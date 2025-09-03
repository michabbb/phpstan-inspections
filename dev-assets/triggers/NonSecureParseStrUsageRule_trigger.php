<?php declare(strict_types=1);

// This file demonstrates the NonSecureParseStrUsageRule
// It contains both positive cases (should trigger errors) and negative cases (should not trigger errors)

// Positive cases - should trigger the rule
parse_str('foo=bar&baz=qux'); // Error: insecure usage
mb_parse_str('foo=bar&baz=qux'); // Error: insecure usage

// Negative cases - should not trigger the rule
parse_str('foo=bar&baz=qux', $result); // OK: second parameter provided
mb_parse_str('foo=bar&baz=qux', $result); // OK: second parameter provided

// Other function calls - should not trigger
parse_url('http://example.com', PHP_URL_HOST); // OK: different function
str_replace('foo', 'bar', 'baz'); // OK: different function