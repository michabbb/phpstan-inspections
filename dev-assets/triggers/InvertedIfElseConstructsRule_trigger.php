<?php

declare(strict_types=1);

/**
 * Trigger file for InvertedIfElseConstructsRule
 * This file contains examples of inverted if-else constructs that should be detected
 */

class TestInvertedIfElseConstructs
{
    public function testInvertedConditions(bool $condition, bool $anotherCondition): void
    {
        // VIOLATION: Inverted condition with NOT operator
        if (!$condition) {
            // False case - this should be in else block
            echo "Condition is false\n";
        } else {
            // True case - this should be in if block
            echo "Condition is true\n";
        }

        // VIOLATION: Inverted condition with === false
        if ($condition === false) {
            // False case - this should be in else block
            echo "Condition is false (=== false)\n";
        } else {
            // True case - this should be in if block
            echo "Condition is true (=== false)\n";
        }

        // VIOLATION: Inverted condition with false ===
        if (false === $condition) {
            // False case - this should be in else block
            echo "Condition is false (false ===)\n";
        } else {
            // True case - this should be in if block
            echo "Condition is true (false ===)\n";
        }

        // OK: Normal condition (no violation expected)
        if ($condition) {
            // True case
            echo "Condition is true (normal)\n";
        } else {
            // False case
            echo "Condition is false (normal)\n";
        }

        // OK: Another normal condition (no violation expected)
        if ($anotherCondition) {
            echo "Another condition is true\n";
        } else {
            echo "Another condition is false\n";
        }

        // VIOLATION: Nested inverted condition
        if (!$anotherCondition) {
            if (!$condition) {
                echo "Both conditions are false\n";
            } else {
                echo "First false, second true\n";
            }
        } else {
            echo "First condition is true\n";
        }
    }
}

// Simple test case (original)
$flag = false;
if (!$flag) {
    echo "Flag is false";
} else {
    echo "Flag is true";
}

// Additional simple test
$condition = true;
if (!$condition) {
    echo "Condition is false";
} else {
    echo "Condition is true";
}