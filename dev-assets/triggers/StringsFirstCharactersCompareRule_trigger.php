<?php
declare(strict_types=1);

// Test cases for StringsFirstCharactersCompareRule
// This rule validates strncmp() and strncasecmp() function calls

class StringsFirstCharactersCompareTest
{
    public function testStrncmpLengthMismatch(): void
    {
        // Should trigger: string length is 5, but length parameter is 3
        $result1 = strncmp('hello', 'world', 3);

        // Should trigger: string length is 7, but length parameter is 10
        $result2 = strncmp('testing', 'another', 10);

        // Should NOT trigger: lengths match
        $result3 = strncmp('hello', 'world', 5);

        // Should NOT trigger: no string literals
        $str1 = 'hello';
        $str2 = 'world';
        $result4 = strncmp($str1, $str2, 3);

        // Should NOT trigger: non-literal length
        $length = 3;
        $result5 = strncmp('hello', 'world', $length);
    }

    public function testStrncasecmpLengthMismatch(): void
    {
        // Should trigger: string length is 5, but length parameter is 2
        $result1 = strncasecmp('Hello', 'World', 2);

        // Should trigger: string length is 8, but length parameter is 5
        $result2 = strncasecmp('Testing', 'Another', 5);

        // Should NOT trigger: lengths match
        $result3 = strncasecmp('Hello', 'World', 5);

        // Should NOT trigger: empty string
        $result4 = strncmp('', 'test', 1);
    }

    public function testEdgeCases(): void
    {
        // Should trigger: single character string with wrong length
        $result1 = strncmp('a', 'b', 2);

        // Should NOT trigger: single character with correct length
        $result2 = strncmp('a', 'b', 1);

        // Should trigger: longer string with shorter length
        $result3 = strncasecmp('verification', 'test', 5);
    }
}