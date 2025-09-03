<?php declare(strict_types=1);

/**
 * Trigger script for IsCountableCanBeUsedRule
 * This script contains examples that should trigger the rule
 */

class TestClass {
    public function testIsCountablePattern(mixed $var): bool
    {
        // This should trigger the rule: is_array() || instanceof Countable
        if (is_array($var) || $var instanceof Countable) {
            return true;
        }

        // Another variation that should trigger
        $result = is_array($var) || $var instanceof Countable;
        if ($result) {
            return true;
        }

        // This should NOT trigger (only is_array, no instanceof)
        if (is_array($var)) {
            return true;
        }

        // This should NOT trigger (only instanceof, no is_array)
        if ($var instanceof Countable) {
            return true;
        }

        return false;
    }

    public function testWithDifferentVariable(mixed $var, mixed $other): bool
    {
        // This should NOT trigger (different variables)
        return is_array($var) || $other instanceof Countable;
    }

    public function testComplexExpression(mixed $var): bool
    {
        // This should trigger the rule
        if (is_array($var) || $var instanceof Countable) {
            return count($var) > 0;
        }

        return false;
    }

    public function testNestedBooleanOr(mixed $var): bool
    {
        // This should trigger the rule (nested in more complex expression)
        if (($var !== null) && (is_array($var) || $var instanceof Countable)) {
            return count($var) > 0;
        }

        return false;
    }
}

// Test with actual data
$testArray = [1, 2, 3];
$testCountable = new ArrayIterator([1, 2, 3]);
$testString = "not countable";

// These should trigger the rule
$testObj = new TestClass();
$testObj->testIsCountablePattern($testArray);
$testObj->testIsCountablePattern($testCountable);
$testObj->testComplexExpression($testArray);
$testObj->testNestedBooleanOr($testCountable);