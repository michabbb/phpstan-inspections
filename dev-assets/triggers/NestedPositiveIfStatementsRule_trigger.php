<?php

declare(strict_types=1);

// This file contains test cases for NestedPositiveIfStatementsRule
// The rule should detect nested if statements that can be merged

class TestNestedPositiveIfStatements {

    public function testNestedIfShouldBeDetected(): void {
        $condition1 = rand(0, 1) === 1;
        $condition2 = rand(0, 1) === 1;
        $condition3 = rand(0, 1) === 1;

        // This should be detected - simple nested if with no else
        if ($condition1) {
            if ($condition2) {
                echo "Both conditions met";
            }
        }

        // This should be detected - nested if with matching else
        if ($condition1) {
            if ($condition2) {
                echo "Both true";
            } else {
                echo "First true, second false";
            }
        } else {
            echo "First false";
        }

        // This should NOT be detected - contains OR
        if ($condition1) {
            if ($condition2 || $condition3) {
                echo "At least one condition met";
            }
        }

        // This should NOT be detected - has elseif
        if ($condition1) {
            if ($condition2) {
                echo "Second true";
            } elseif ($condition3) {
                echo "Third true";
            }
        }

        // This should NOT be detected - multiple statements
        if ($condition1) {
            echo "First statement";
            if ($condition2) {
                echo "Nested statement";
            }
        }
    }
}