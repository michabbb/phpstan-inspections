<?php

declare(strict_types=1);

// Test cases for GetTypeMissUseRule

function triggerGettypeMissUseRule(): void
{
    $variable = 'test';

    // POSITIVE CASES - should trigger rule (replaceable with is_* functions)
    
    // Basic equality checks
    if (gettype($variable) === 'string') {
        // Should suggest: is_string($variable)
    }
    
    if (gettype($variable) == 'integer') {
        // Should suggest: is_int($variable)
    }
    
    if (gettype($variable) === 'boolean') {
        // Should suggest: is_bool($variable)
    }
    
    if (gettype($variable) === 'array') {
        // Should suggest: is_array($variable)
    }
    
    if (gettype($variable) === 'object') {
        // Should suggest: is_object($variable)
    }
    
    if (gettype($variable) === 'resource') {
        // Should suggest: is_resource($variable)
    }
    
    if (gettype($variable) === 'double') {
        // Should suggest: is_float($variable)
    }
    
    if (gettype($variable) === 'NULL') {
        // Should suggest: is_null($variable)
    }
    
    // Inequality checks (should use negated is_* functions)
    if (gettype($variable) !== 'string') {
        // Should suggest: !is_string($variable)
    }
    
    if (gettype($variable) != 'integer') {
        // Should suggest: !is_int($variable)
    }
    
    // Reversed operands
    if ('string' === gettype($variable)) {
        // Should suggest: is_string($variable)
    }
    
    if ('integer' != gettype($variable)) {
        // Should suggest: !is_int($variable)
    }
    
    // INVALID TYPE CASES - should trigger error for invalid type strings
    if (gettype($variable) === 'invalid_type') {
        // Should report: 'invalid_type' is not a value returned by 'gettype(...)'
    }
    
    if (gettype($variable) === 'float') {
        // Should report: 'float' is not a value returned by 'gettype(...)' (correct is 'double')
    }
    
    // NEGATIVE CASES - should NOT trigger rule
    
    // Valid types without is_* equivalents (should not trigger)
    if (gettype($variable) === 'unknown type') {
        // No rule violation - no is_* equivalent
    }
    
    if (gettype($variable) === 'resource (closed)') {
        // No rule violation - no is_* equivalent
    }
    
    // Non-equality comparisons (should not trigger)
    $typeString = gettype($variable);
    if (strlen($typeString) > 5) {
        // No rule violation - not an equality comparison with literal
    }
    
    // gettype() not in comparison (should not trigger)
    $type = gettype($variable);
    echo $type;
    
    // gettype() with wrong number of arguments (should not trigger this rule)
    // This would be a different error, not our concern
    // gettype(); // syntax error anyway
    // gettype($a, $b); // wrong argument count
    
    // Comparison with non-literal (should not trigger)
    $expected_type = 'string';
    if (gettype($variable) === $expected_type) {
        // No rule violation - not comparing with string literal
    }
    
    // Different function (should not trigger)
    if (is_string($variable)) {
        // No rule violation - already using is_* function
    }
}
