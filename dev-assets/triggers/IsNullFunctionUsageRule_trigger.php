<?php declare(strict_types=1);

/**
 * Trigger script for IsNullFunctionUsageRule
 * This file contains various test cases that should trigger the rule
 */

class TestClass {
    public function testDirectUsage(): void {
        $var = null;

        // Direct usage - should trigger
        if (is_null($var)) {
            echo "Variable is null";
        }

        // Negation - should trigger
        if (!is_null($var)) {
            echo "Variable is not null";
        }
    }

    public function testComparisonsWithTrue(): void {
        $var = null;

        // Comparison with true using == - should trigger
        if (is_null($var) == true) {
            echo "Variable is null";
        }

        // Comparison with true using === - should trigger
        if (is_null($var) === true) {
            echo "Variable is null";
        }

        // Comparison with true using != - should trigger
        if (is_null($var) != true) {
            echo "Variable is not null";
        }

        // Comparison with true using !== - should trigger
        if (is_null($var) !== true) {
            echo "Variable is not null";
        }
    }

    public function testComparisonsWithFalse(): void {
        $var = null;

        // Comparison with false using == - should trigger
        if (is_null($var) == false) {
            echo "Variable is not null";
        }

        // Comparison with false using === - should trigger
        if (is_null($var) === false) {
            echo "Variable is not null";
        }

        // Comparison with false using != - should trigger
        if (is_null($var) != false) {
            echo "Variable is null";
        }

        // Comparison with false using !== - should trigger
        if (is_null($var) !== false) {
            echo "Variable is null";
        }
    }

    public function testComplexExpressions(): void {
        $var = null;
        $other = "test";

        // Complex expression in assignment - should trigger
        $result = is_null($var);

        // Ternary with is_null - should trigger
        $value = is_null($var) ? "null" : "not null";

        // Binary operation - should trigger
        if (is_null($var) && $other === "test") {
            echo "Both conditions met";
        }
    }

    public function testNonTriggeringCases(): void {
        $var = null;

        // These should NOT trigger the rule
        if ($var === null) {
            echo "Using === null";
        }

        if ($var !== null) {
            echo "Using !== null";
        }

        // Different function names
        if (isset($var)) {
            echo "Using isset";
        }

        if (empty($var)) {
            echo "Using empty";
        }
    }
}