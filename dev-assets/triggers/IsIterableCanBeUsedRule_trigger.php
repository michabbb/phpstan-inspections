<?php

declare(strict_types=1);

/**
 * Test cases for IsIterableCanBeUsedRule
 * 
 * This file contains examples that should trigger the rule (positive cases)
 * and examples that should not trigger the rule (negative cases).
 */

function testPositiveCases($data, $input, $value): void
{
    // Should trigger: exact pattern is_array() || instanceof Traversable
    if (is_array($data) || $data instanceof Traversable) {
        echo 'Found iterable data';
    }
    
    // Should trigger: same pattern with different variable
    if (is_array($input) || $input instanceof Traversable) {
        echo 'Found iterable input';
    }
    
    // Should trigger: pattern with parentheses
    if ((is_array($value) || $value instanceof Traversable)) {
        echo 'Found iterable value';  
    }
    
    // Should trigger: pattern in complex boolean expression
    if (($data !== null) && (is_array($data) || $data instanceof Traversable)) {
        echo 'Non-null iterable data';
    }
}

function testNegativeCases($data, $input, $different): void
{
    // Should NOT trigger: only is_array without instanceof Traversable
    if (is_array($data)) {
        echo 'Just array check';
    }
    
    // Should NOT trigger: only instanceof Traversable without is_array
    if ($data instanceof Traversable) {
        echo 'Just traversable check';
    }
    
    // Should NOT trigger: different variables in checks
    if (is_array($data) || $input instanceof Traversable) {
        echo 'Different variables';
    }
    
    // Should NOT trigger: wrong class name
    if (is_array($data) || $data instanceof Iterator) {
        echo 'Wrong class';
    }
    
    // Should NOT trigger: AND instead of OR
    if (is_array($data) && $data instanceof Traversable) {
        echo 'Using AND instead of OR';
    }
    
    // Should NOT trigger: is_iterable already used
    if (is_iterable($data)) {
        echo 'Already using is_iterable';
    }
    
    // Should NOT trigger: different function
    if (is_object($data) || $data instanceof Traversable) {
        echo 'Not is_array';
    }
}

function testEdgeCases($x): void  
{
    // Should trigger: nested OR expressions
    if (is_array($x) || $x instanceof Traversable || $x === null) {
        echo 'Nested OR with null check';
    }
    
    // Should NOT trigger: missing argument to is_array
    if (is_array() || $x instanceof Traversable) {
        echo 'Missing argument';
    }
}

// Variables for testing
$testData = [1, 2, 3];
$testIterator = new ArrayIterator([1, 2, 3]);
$testString = 'not iterable';

testPositiveCases($testData, $testIterator, $testString);
testNegativeCases($testData, $testIterator, $testString);
testEdgeCases($testData);