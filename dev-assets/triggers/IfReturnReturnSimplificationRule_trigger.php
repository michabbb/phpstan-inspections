<?php declare(strict_types=1);

/**
 * Trigger file for IfReturnReturnSimplificationRule
 * Contains patterns that should be detected by the rule
 */

class IfReturnReturnSimplificationTest
{
    // POSITIVE CASE 1: If-else with return true/false
    public function testDirectPattern(int $a, int $b): bool
    {
        if ($a == $b) {
            return true;
        } else {
            return false;
        }
    }

    // POSITIVE CASE 2: If-else with return false/true (should suggest negation)
    public function testReversePattern(int $a, int $b): bool
    {
        if ($a == $b) {
            return false;
        } else {
            return true;
        }
    }

    // POSITIVE CASE 3: If with return true, followed by return false
    public function testIfReturnReturnPattern(int $a, int $b): bool
    {
        if ($a > $b) {
            return true;
        }
        return false;
    }

    // POSITIVE CASE 4: If with return false, followed by return true (should suggest negation)
    public function testIfReturnReturnReversePattern(int $a, int $b): bool
    {
        if ($a > $b) {
            return false;
        }
        return true;
    }

    // NEGATIVE CASE 1: If with non-binary condition (should not trigger)
    public function testNonBinaryCondition(bool $condition): bool
    {
        if ($condition) {
            return true;
        } else {
            return false;
        }
    }

    // NEGATIVE CASE 2: If with return of non-boolean value (should not trigger)
    public function testNonBooleanReturn(int $a, int $b): int
    {
        if ($a == $b) {
            return 1;
        } else {
            return 0;
        }
    }

    // NEGATIVE CASE 3: If with multiple statements in body (should not trigger)
    public function testMultipleStatements(int $a, int $b): bool
    {
        if ($a == $b) {
            echo "Equal";
            return true;
        } else {
            return false;
        }
    }

    // NEGATIVE CASE 4: If without else and no following return (should not trigger)
    public function testNoElseNoFollowingReturn(int $a, int $b): void
    {
        if ($a == $b) {
            return true;
        }
        echo "Not equal";
    }

    // POSITIVE CASE 5: More complex binary expression
    public function testComplexBinaryExpression(int $a, int $b, int $c): bool
    {
        if ($a > $b && $b < $c) {
            return true;
        } else {
            return false;
        }
    }

    // POSITIVE CASE 6: Property access in condition
    public function testPropertyAccess($obj): bool
    {
        if ($obj->value == 42) {
            return true;
        }
        return false;
    }

    // POSITIVE CASE 7: Method call in condition
    public function testMethodCall($obj): bool
    {
        if ($obj->isValid()) {
            return false;
        }
        return true;
    }
}