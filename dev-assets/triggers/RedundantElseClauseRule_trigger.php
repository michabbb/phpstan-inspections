<?php declare(strict_types=1);

/**
 * Trigger script for RedundantElseClauseRule testing
 *
 * This file contains test cases that should trigger the RedundantElseClauseRule
 * when the if body ends with return, break, continue, throw, or exit statements.
 */

class TestRedundantElseClause
{
    public function testReturnWithElse(): void
    {
        $value = 10;

        // This should trigger: if ends with return, else is redundant
        if ($value > 5) {
            echo "Value is greater than 5";
            return;
        } else {
            echo "Value is not greater than 5";
        }
    }

    public function testReturnWithElseIf(): void
    {
        $value = 10;

        // This should trigger: if ends with return, elseif is redundant
        if ($value > 5) {
            echo "Value is greater than 5";
            return;
        } elseif ($value > 0) {
            echo "Value is positive";
        } else {
            echo "Value is not positive";
        }
    }

    public function testBreakWithElse(): void
    {
        $array = [1, 2, 3, 4, 5];

        // This should trigger: if ends with break, else is redundant
        foreach ($array as $item) {
            if ($item === 3) {
                echo "Found 3";
                break;
            } else {
                echo "Not 3";
            }
        }
    }

    public function testContinueWithElse(): void
    {
        $array = [1, 2, 3, 4, 5];

        // This should trigger: if ends with continue, else is redundant
        foreach ($array as $item) {
            if ($item % 2 === 0) {
                echo "Even number";
                continue;
            } else {
                echo "Odd number";
            }
        }
    }

    public function testThrowWithElse(): void
    {
        $value = -1;

        // This should trigger: if ends with throw, else is redundant
        if ($value < 0) {
            throw new InvalidArgumentException("Value cannot be negative");
        } else {
            echo "Value is valid";
        }
    }

    public function testExitWithElse(): void
    {
        $value = 0;

        // This should trigger: if ends with exit, else is redundant
        if ($value === 0) {
            echo "Exiting program";
            exit(1);
        } else {
            echo "Continuing program";
        }
    }

    public function testMultipleElseIfs(): void
    {
        $value = 15;

        // This should trigger: if ends with return, all elseif/else are redundant
        if ($value > 10) {
            echo "Value is greater than 10";
            return;
        } elseif ($value > 5) {
            echo "Value is greater than 5";
        } elseif ($value > 0) {
            echo "Value is positive";
        } else {
            echo "Value is zero or negative";
        }
    }

    // Negative test cases - these should NOT trigger the rule

    public function testNoReturnNoTrigger(): void
    {
        $value = 10;

        // This should NOT trigger: if doesn't end with return/break/continue/throw/exit
        if ($value > 5) {
            echo "Value is greater than 5";
        } else {
            echo "Value is not greater than 5";
        }
    }

    public function testElseIfChainNoTrigger(): void
    {
        $value = 10;

        // This should NOT trigger: rule skips else-if chains
        if ($value > 10) {
            echo "Greater than 10";
        } elseif ($value > 5) {
            echo "Greater than 5";
        } else {
            echo "5 or less";
        }
    }

    public function testUnbracedBodyNoTrigger(): void
    {
        $value = 10;

        // This should NOT trigger: rule only checks braced bodies
        if ($value > 5)
            echo "Value is greater than 5";
        else
            echo "Value is not greater than 5";
    }
}