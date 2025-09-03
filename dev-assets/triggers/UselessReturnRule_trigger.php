<?php

// Test file for UselessReturnRule
// This file contains examples that should trigger the rule

class TestUselessReturn
{
    // POSITIVE CASE 1: Senseless return at end of method
    public function senselessReturn(): void
    {
        echo "Hello";
        return; // This should trigger: "Senseless statement: return null implicitly or safely remove it."
    }

    // POSITIVE CASE 2: Confusing assignment in return
    public function confusingAssignment(): int
    {
        $value = 42;
        return $result = $value; // This should trigger: "Assignment here is not making much sense."
    }

    // POSITIVE CASE 3: Another confusing assignment
    public function anotherConfusingAssignment(): string
    {
        $name = "test";
        return $output = $name; // This should trigger: "Assignment here is not making much sense."
    }

    // NEGATIVE CASE 1: Return with value is fine
    public function normalReturn(): int
    {
        return 42;
    }

    // NEGATIVE CASE 2: Return in middle of function is fine
    public function returnInMiddle(): void
    {
        if (true) {
            return;
        }
        echo "This won't execute";
    }

    // NEGATIVE CASE 3: Assignment to parameter by reference should not trigger
    public function assignmentToReferenceParam(string &$output): string
    {
        return $output = "modified"; // This should NOT trigger because $output is passed by reference
    }

    // NEGATIVE CASE 4: Assignment to static variable should not trigger
    public function assignmentToStatic(): int
    {
        static $counter = 0;
        return $counter = $counter + 1; // This should NOT trigger because $counter is static
    }

    // NEGATIVE CASE 5: Assignment used in finally block should not trigger
    public function assignmentUsedInFinally(): string
    {
        try {
            return $temp = "value"; // This should NOT trigger because $temp is used in finally
        } finally {
            echo $temp;
        }
    }
}

// Test function (not method)
function testFunctionSenseless(): void
{
    echo "test";
    return; // This should trigger: "Senseless statement: return null implicitly or safely remove it."
}

function testFunctionNormal(): int
{
    return 123;
}

function testFunctionConfusing(): string
{
    $value = "test";
    return $result = $value; // This should trigger: "Assignment here is not making much sense."
}