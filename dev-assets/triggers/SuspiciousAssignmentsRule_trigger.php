<?php

// Trigger file for SuspiciousAssignmentsRule
// This file contains examples of all the patterns that should be detected

// === SEQUENTIAL ASSIGNMENTS ===
// These should trigger assignment.sequential errors

function testSequentialAssignments() {
    $a = 1;
    $a = 2; // Should trigger: $a is immediately overridden

    $b = 'hello';
    $b = 'world'; // Should trigger: $b is immediately overridden

    // Valid cases that should NOT trigger
    $c = 1;
    echo $c; // Variable is used, so no error
    $c = 2; // This is fine

    $d = [];
    $d[] = 'item'; // Array push, should not trigger
    $d = [1, 2, 3]; // This should not trigger because it's not immediate override of the same variable
}

// === SELF ASSIGNMENTS ===
// These should trigger assignment.self errors

function testSelfAssignments() {
    $a = 5;
    $a += $a + 1; // Should trigger: Related operation being applied to the same variable

    $b = 10;
    $b *= $b * 2; // Should trigger: Related operation being applied to the same variable

    $c = 'hello';
    $c .= $c . ' world'; // Should trigger: Related operation being applied to the same variable

    // Valid cases
    $d = 5;
    $d += 1; // This is fine - not using the same variable in the expression

    $e = 10;
    $e *= 2; // This is fine
}

// === PARAMETER OVERRIDE ===
// These should trigger parameter.immediateOverride errors

function testParameterOverride($param1, $param2) {
    $param1 = 'new value'; // Should trigger: This variable name has already been declared previously without being used.

    // Valid case
    echo $param2; // Parameter is used before reassignment
    $param2 = 'new value'; // This is fine
}

// === SUSPICIOUS OPERATOR FORMATTING ===
// These should trigger assignment.operatorFormatting errors

function testSuspiciousOperatorFormatting() {
    $a = 5;
    $a = + $a; // Should trigger: Probably '+=' operator should be used here

    $b = 10;
    $b = - $b; // Should trigger: Probably '-=' operator should be used here

    $c = true;
    $c = ! $c; // Should trigger: Probably '!=' operator should be used here

    // Valid cases
    $d = 5;
    $d += $d; // This is fine

    $e = 10;
    $e -= $e; // This is fine
}

// === SWITCH FALLTHROUGH ===
// These should trigger switch.fallthrough errors

function testSwitchFallthrough($value) {
    switch ($value) {
        case 1:
            $result = 'one';
            // Missing break - should trigger when next case assigns to same variable
        case 2:
            $result = 'two'; // Should trigger: Overrides value from a preceding case
            break;

        case 3:
            $output = 'three';
            break; // Has break, so next case is fine
        case 4:
            $output = 'four'; // Should NOT trigger because previous case had break
            break;
    }

    // Valid case with proper breaks
    switch ($value) {
        case 5:
            $valid = 'five';
            break;
        case 6:
            $valid = 'six'; // This is fine because previous case had break
            break;
    }
}

// === COMPLEX EXAMPLE ===
function complexExample($input) {
    // Sequential assignment
    $data = [];
    $data = processData($input); // Should trigger

    // Self assignment
    $counter = 0;
    $counter += $counter + 1; // Should trigger

    // Parameter override
    $input = strtoupper($input); // Should trigger

    return $data;
}

function processData($data) {
    return array_map('strtolower', $data);
}