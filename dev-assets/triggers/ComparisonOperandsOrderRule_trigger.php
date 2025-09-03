<?php

// This file demonstrates the ComparisonOperandsOrderRule
// With useYodaStyle: true, it should report violations for regular style

class TestComparisonOperandsOrder
{
    public function testMethod($value)
    {
        // These should trigger violations (regular style when Yoda is preferred)
        if ($value === 0) { // VIOLATION: should be 0 === $value
            return true;
        }

        if ($value == 'test') { // VIOLATION: should be 'test' == $value
            return false;
        }

        if ($value !== null) { // VIOLATION: should be null !== $value
            return null;
        }

        // These should NOT trigger violations (already Yoda style)
        if (0 === $value) { // OK: Yoda style
            return true;
        }

        if ('test' == $value) { // OK: Yoda style
            return false;
        }

        if (null !== $value) { // OK: Yoda style
            return null;
        }

        // These should NOT trigger violations (no constants involved)
        if ($value === $otherValue) { // OK: both variables
            return true;
        }

        // Mixed cases with constants on both sides should not trigger
        if (0 === 0) { // OK: both constants
            return true;
        }

        return false;
    }
}