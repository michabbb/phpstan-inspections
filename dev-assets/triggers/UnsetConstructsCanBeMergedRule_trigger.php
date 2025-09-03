<?php

declare(strict_types=1);

// Test cases for UnsetConstructsCanBeMergedRule

function testUnsetMerging(): void
{
    $var1 = 'test1';
    $var2 = 'test2';
    $var3 = 'test3';
    $var4 = 'test4';
    
    // Case 1: Consecutive unset statements (should trigger error)
    unset($var1);
    unset($var2); // This should trigger the rule
    
    // Re-initialize variables for next test
    $var1 = 'test1';
    $var2 = 'test2';
    $var3 = 'test3';
    
    // Case 2: More than two consecutive unset statements
    unset($var1);
    unset($var2); // This should trigger the rule
    unset($var3); // This should also trigger the rule
    
    // Re-initialize variables for next test
    $var1 = 'test1';
    $var2 = 'test2';
    
    // Case 3: Non-consecutive unset statements (should NOT trigger)
    unset($var1);
    $someOtherOperation = true;
    unset($var2); // This should NOT trigger because there's a statement in between
    
    // Case 4: Single unset statement (should NOT trigger)
    unset($var4);
}

function testWithComments(): void
{
    $var1 = 'test1';
    $var2 = 'test2';
    
    // Case 5: Consecutive unset with comments in between
    unset($var1);
    // This is a comment
    unset($var2); // This should still trigger the rule (ignoring comments)
}

class TestClass
{
    public function testInClassContext(): void
    {
        $property1 = 'test1';
        $property2 = 'test2';
        
        // Case 6: Consecutive unset in class method
        unset($property1);
        unset($property2); // This should trigger the rule
    }
}

// Case 7: Multiple consecutive unset statements
function testMultipleConsecutive(): void
{
    $a = 1;
    $b = 2;
    $c = 3;
    $d = 4;
    
    unset($a);
    unset($b); // Should trigger
    unset($c); // Should trigger  
    unset($d); // Should trigger
}