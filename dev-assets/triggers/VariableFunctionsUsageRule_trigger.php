<?php
declare(strict_types=1);

// Test file for VariableFunctionsUsageRule
// This file contains variable function usage patterns that should trigger the rule

class TestClass {
    public function testMethod($arg) {
        return "test: $arg";
    }

    public static function staticMethod($arg) {
        return "static: $arg";
    }
}

function testFunction($arg) {
    return "function: $arg";
}

// Test cases that SHOULD trigger the rule

// Case 1: call_user_func_array with array(...) that can be simplified to call_user_func
$result1 = call_user_func_array('testFunction', array('hello')); // Should trigger: can be call_user_func('testFunction', 'hello')

// Case 2: call_user_func with array callable that can be simplified to method call
$result2 = call_user_func(['TestClass', 'testMethod'], 'test'); // Should trigger: can be TestClass::testMethod('test')

// Case 3: call_user_func with array callable for static method
$result3 = call_user_func(['TestClass', 'staticMethod'], 'static'); // Should trigger: can be TestClass::staticMethod('static')

// Case 4: forward_static_call_array with array that can be simplified
$result4 = forward_static_call_array('testFunction', array('array')); // Should trigger: can be forward_static_call('testFunction', 'array')

// Case 5: call_user_func_array with multiple arguments
$result5 = call_user_func_array('sprintf', array('%s %s', 'hello', 'world')); // Should trigger: can be call_user_func('sprintf', '%s %s', 'hello', 'world')

// Test cases that should NOT trigger the rule (edge cases)

// Case 6: call_user_func with string literal (should not trigger)
$result6 = call_user_func('testFunction', 'literal'); // OK - direct string

// Case 7: call_user_func_array with string literal (should not trigger)
$result7 = call_user_func_array('sprintf', array('%s', 'test')); // OK - direct string

// Case 8: forward_static_call with string literal (should not trigger)
$result8 = forward_static_call('testFunction', 'static_literal'); // OK - direct string

// Case 9: forward_static_call_array with string literal (should not trigger)
$result9 = forward_static_call_array('sprintf', array('%s', 'test')); // OK - direct string