<?php

declare(strict_types=1);

// Test cases for ShortListSyntaxCanBeUsedRule
// This rule detects when short list syntax can be used instead of list() construct

class ShortListSyntaxTest
{
    public function testListAssignment(): void
    {
        $array = [1, 2, 3];

        // This should trigger the rule - can be replaced with [$a, $b] = $array;
        list($a, $b) = $array;

        // This should trigger the rule - can be replaced with [$x, $y, $z] = $array;
        list($x, $y, $z) = $array;

        // This should NOT trigger the rule - nested list
        list(list($nested1, $nested2), $other) = [[1, 2], 3];

        // This should NOT trigger the rule - part of larger expression
        $result = list($p, $q) = $array;
    }

    public function testForeachListUsage(): void
    {
        $arrays = [[1, 2], [3, 4], [5, 6]];

        // This should trigger the rule - can be replaced with foreach ($arrays as [$first, $second])
        foreach ($arrays as list($first, $second)) {
            echo $first + $second;
        }

        // This should trigger the rule - can be replaced with foreach ($arrays as [$a, $b, $c])
        foreach ($arrays as list($a, $b, $c)) {
            // Handle case where array might have fewer elements
        }

        // This should NOT trigger the rule - nested list in foreach
        foreach ($arrays as list(list($nested), $other)) {
            // Complex nested structure
        }
    }

    public function testMixedUsage(): void
    {
        $data = [[1, 2], [3, 4]];

        // Both assignment and foreach should trigger the rule
        list($x, $y) = $data[0];

        foreach ($data as list($a, $b)) {
            echo $a . $b;
        }
    }
}