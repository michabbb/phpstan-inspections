<?php

declare(strict_types=1);

/**
 * Trigger file for TypeUnsafeArraySearchRule
 * 
 * This file contains examples that should trigger the rule (positive cases)
 * and examples that should NOT trigger the rule (negative cases).
 */

// POSITIVE CASES - Should trigger the rule

// Basic case: in_array without strict parameter
function testInArrayWithoutStrict(): bool
{
    $needle = 'test';
    $haystack = ['test', 'example', 'demo'];
    
    return in_array($needle, $haystack); // Should trigger rule
}

// Basic case: array_search without strict parameter
function testArraySearchWithoutStrict(): int|string|false
{
    $needle = 42;
    $haystack = [1, 2, 42, 3];
    
    return array_search($needle, $haystack); // Should trigger rule
}

// Mixed types that could cause issues
function testMixedTypes(): bool
{
    $needle = '1';
    $haystack = [1, 2, 3]; // numeric array, string needle
    
    return in_array($needle, $haystack); // Should trigger rule
}

// NEGATIVE CASES - Should NOT trigger the rule

// Already has strict parameter - should not trigger
function testInArrayWithStrict(): bool
{
    $needle = 'test';
    $haystack = ['test', 'example', 'demo'];
    
    return in_array($needle, $haystack, true); // Should NOT trigger rule
}

// Already has strict parameter - should not trigger
function testArraySearchWithStrict(): int|string|false
{
    $needle = 42;
    $haystack = [1, 2, 42, 3];
    
    return array_search($needle, $haystack, true); // Should NOT trigger rule
}

// False positive prevention: direct array of non-numeric string literals  
function testStringLiteralArrayDirect(): bool
{
    $needle = 'admin';
    
    return in_array($needle, ['admin', 'user', 'guest']); // Should NOT trigger rule (direct literal)
}

// But should trigger if array is in variable
function testStringLiteralArrayVariable(): bool
{
    $needle = 'admin';
    $haystack = ['admin', 'user', 'guest']; // Array of string literals
    
    return in_array($needle, $haystack); // Should trigger rule (variable)
}

// Single parameter (different function signature)
function testSingleParameter(): bool
{
    $haystack = [1, 2, 3];
    
    return array_key_exists('key', $haystack); // Different function, should not trigger
}

// More than 2 parameters (already has strict)
function testThreeParameters(): bool
{
    $needle = 'test';
    $haystack = ['test'];
    
    return in_array($needle, $haystack, false); // Should NOT trigger rule
}

// Test with numeric string literals in array (should trigger)
function testNumericStringLiterals(): bool
{
    $needle = '123';
    $haystack = ['123', '456', '789']; // Contains numeric strings
    
    return in_array($needle, $haystack); // Should trigger rule
}

// Test with empty array elements (should trigger)
function testEmptyElements(): bool
{
    $needle = '';
    $haystack = ['', 'test']; // Contains empty string
    
    return in_array($needle, $haystack); // Should trigger rule
}