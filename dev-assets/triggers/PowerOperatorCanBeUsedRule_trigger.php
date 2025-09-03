<?php declare(strict_types=1);

/**
 * Trigger script for PowerOperatorCanBeUsedRule
 * Tests various pow() function calls that should be replaced with ** operator
 */

class PowerOperatorTest
{
    public function testSimplePowerOperations(): void
    {
        // Simple cases that should trigger the rule
        $result1 = pow(2, 3);           // Should suggest: 2 ** 3
        $result2 = pow(10, 2);          // Should suggest: 10 ** 2
        $result3 = pow(5, 0);           // Should suggest: 5 ** 0

        // With variables
        $base = 4;
        $exponent = 2;
        $result4 = pow($base, $exponent);  // Should suggest: $base ** $exponent

        // With function calls
        $result5 = pow(rand(1, 10), 2);    // Should suggest: rand(1, 10) ** 2

        // Complex expressions
        $result6 = pow(2 * 3, 4 + 1);      // Should suggest: (2 * 3) ** (4 + 1)

        // Cases that need parentheses in binary operations
        $result7 = 5 + pow(2, 3);          // Should suggest: 5 + (2 ** 3)
        $result8 = pow(2, 3) * 4;          // Should suggest: (2 ** 3) * 4

        // Nested pow calls
        $result9 = pow(pow(2, 3), 4);      // Should suggest: ((2 ** 3) ** 4)
    }

    public function testEdgeCases(): void
    {
        // These should NOT trigger the rule (different function names)
        $result1 = power(2, 3);            // Different function name
        $result2 = pow();                  // No arguments
        $result3 = pow(2);                 // Only one argument

        // Dynamic function calls (should not trigger)
        $func = 'pow';
        $result4 = $func(2, 3);
    }
}