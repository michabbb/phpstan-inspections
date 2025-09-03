<?php

declare(strict_types=1);

// Positive cases - should trigger the rule

function positiveCases(): void
{
    $string = 'hello world';

    // Case 1: Nested array access (string offset used as array)
    $result1 = $string[0][1]; // Should trigger: cannot use string offset as an array

    // Case 2: Push operation without index
    $string[] = 'test'; // Should trigger: [] operator not supported for strings

    // More examples
    $anotherString = 'test';
    $nested = $anotherString[$i = 0][$j = 1]; // Should trigger
}

// Negative cases - should NOT trigger the rule

function negativeCases(): void
{
    $array = [1, 2, 3];

    // Normal array access - should not trigger
    $value = $array[0];
    $nestedArray = $array[0][1]; // Valid for multidimensional arrays

    // String operations that are fine
    $string = 'hello';
    $char = $string[0]; // Single character access is OK
    $substr = substr($string, 0, 1); // substr is fine

    // Non-string variables
    $number = 42;
    $float = 3.14;
    $bool = true;

    // These should not trigger because they're not strings
    // $number[0]; // Would be invalid PHP anyway
    // $float[]; // Would be invalid PHP anyway
}