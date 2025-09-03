<?php

declare(strict_types=1);

// Test file for SuspiciousBinaryOperationRule
// This file contains examples that should trigger various strategies

class TestClass {
    public function testIdenticalOperands(): void {
        $a = 5;
        $b = 10;

        // Identical operands - should trigger
        if ($a == $a) {
            echo "This should trigger identical operands";
        }

        // Valid comparison - should not trigger
        if ($a == $b) {
            echo "This is valid";
        }
    }

    public function testInstanceOfTrait(): void {
        $obj = new stdClass();

        // instanceof with trait - should trigger (if MyTrait exists and is a trait)
        if ($obj instanceof MyTrait) {
            echo "This should trigger instanceof trait";
        }

        // Valid instanceof - should not trigger
        if ($obj instanceof stdClass) {
            echo "This is valid";
        }
    }

    public function testConcatenationWithArray(): void {
        $arr = [1, 2, 3];
        $str = "test";

        // Concatenation with array - should trigger
        $result = $arr . $str;

        // Valid concatenation - should not trigger
        $result2 = $str . "more";
    }

    public function testHardcodedConstants(): void {
        $condition = true;

        // true && something - senseless - should trigger
        if (true && $condition) {
            echo "This should trigger senseless true";
        }

        // false && something - enforces result - should trigger
        if (false && $condition) {
            echo "This should trigger enforces false";
        }

        // true || something - enforces result - should trigger
        if (true || $condition) {
            echo "This should trigger enforces true";
        }

        // false || something - senseless - should trigger
        if (false || $condition) {
            echo "This should trigger senseless false";
        }

        // Valid conditions - should not trigger
        if ($condition && true) {
            echo "This is valid";
        }
    }

    public function testNullCoalescingPrecedence(): void {
        $value = null;

        // Unary operator with ?? - should trigger
        $result = !$value ?? 'default';

        // Valid ?? usage - should not trigger
        $result2 = $value ?? 'default';
    }

    public function testEqualsInAssignmentContext(): void {
        $a = 5;
        $b = 10;

        // == in statement context (likely meant =) - should trigger
        $a == $b;

        // Valid comparison in if - should not trigger
        if ($a == $b) {
            echo "Valid";
        }
    }

    public function testGreaterOrEqualInArray(): void {
        $key = "test";

        // >= in array context (likely meant =>) - should trigger
        $arr = [
            $key >= "value"
        ];

        // Valid array - should not trigger
        $arr2 = [
            $key => "value"
        ];
    }

    public function testNullableArgumentComparison(): void {
        $value = null;

        // Nullable comparison with < in negated context - should trigger
        if (!($value < 5)) {
            echo "This should trigger nullable comparison";
        }

        // Valid comparison - should not trigger
        if ($value < 5) {
            echo "This is valid";
        }
    }

    public function testUnclearOperationsPriority(): void {
        $a = true;
        $b = false;

        // Mixed && and || without parentheses - should trigger
        if ($a && $b || $a) {
            echo "This should trigger unclear priority";
        }

        // Clear precedence with parentheses - should not trigger
        if (($a && $b) || $a) {
            echo "This is valid";
        }
    }

    public function testMisplacedOperator(): void {
        // Misplaced operator as last argument - should trigger
        $this->someFunction($a == $b);

        // Valid usage - should not trigger
        if ($a == $b) {
            echo "Valid";
        }
    }
}

// Dummy trait for testing
trait MyTrait {
}