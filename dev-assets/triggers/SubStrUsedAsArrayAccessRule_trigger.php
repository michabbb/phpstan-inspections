<?php

declare(strict_types=1);

// Positive cases - should trigger the rule
function positiveCases(string $str, int $offset): void
{
    // Basic case: substr($string, $offset, 1) should be $string[$offset]
    $char1 = substr($str, $offset, 1);

    // Negative offset case: substr($string, -1, 1) should be $string[strlen($string) - 1]
    $lastChar = substr($str, -1, 1);

    // Another negative offset
    $secondLast = substr($str, -2, 1);
}

// Negative cases - should NOT trigger the rule
function negativeCases(string $str, int $offset, int $length): void
{
    // Wrong length (not 1)
    $chars = substr($str, $offset, 2);

    // Wrong number of arguments (only 2)
    $fromStart = substr($str, $offset);

    // Non-string source (integer)
    $numStr = (string) 123;
    $char = substr($numStr, 0, 1); // This might not trigger because we check for string type

    // Dynamic length
    $char = substr($str, $offset, $length);

    // Non-variable source (literal)
    $char = substr('hello', 0, 1);
}

// Edge cases
function edgeCases(): void
{
    // Variable from function call (might not resolve to string type)
    $result = someFunction();
    if (is_string($result)) {
        $char = substr($result, 0, 1); // Should trigger if type is known
    }
}