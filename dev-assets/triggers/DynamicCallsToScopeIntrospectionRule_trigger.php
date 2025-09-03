<?php
declare(strict_types=1);

// Test file for DynamicCallsToScopeIntrospectionRule
// This file contains dynamic calls to scope introspection functions that should trigger the rule

// Test data
$name = 'John';
$age = 30;
$city = 'New York';
$data = ['name' => $name, 'age' => $age];

// Case 1: Direct dynamic calls to scope introspection functions
// These should trigger the rule

$func1 = 'compact';
$result1 = $func1('name', 'age'); // Dynamic call to compact()

$func2 = 'extract';
$result2 = $func2($data); // Dynamic call to extract()

$func3 = 'func_get_args';
$result3 = $func3(); // Dynamic call to func_get_args()

$func4 = 'func_get_arg';
$result4 = $func4(0); // Dynamic call to func_get_arg()

$func5 = 'func_num_args';
$result5 = $func5(); // Dynamic call to func_num_args()

$func6 = 'get_defined_vars';
$result6 = $func6(); // Dynamic call to get_defined_vars()

$func7 = 'parse_str';
$result7 = $func7('name=John&age=30'); // Dynamic call to parse_str()

$func8 = 'mb_parse_str';
$result8 = $func8('name=John&age=30'); // Dynamic call to mb_parse_str()

// Case 2: Callback-based dynamic calls using array functions
// These should also trigger the rule

$callback1 = 'compact';
$result9 = array_map($callback1, [['name', 'age']]); // array_map with compact callback

$callback2 = 'extract';
$result10 = array_filter($data, $callback2); // array_filter with extract callback

$callback3 = 'func_get_args';
$result11 = array_walk($data, $callback3); // array_walk with func_get_args callback

$callback4 = 'parse_str';
$result12 = array_reduce(['name=John', 'age=30'], $callback4); // array_reduce with parse_str callback

// Case 3: Using call_user_func and call_user_func_array
// These should trigger the rule

$result13 = call_user_func('compact', 'name', 'age'); // call_user_func with compact
$result14 = call_user_func_array('extract', [$data]); // call_user_func_array with extract

// Case 4: Edge cases that should NOT trigger the rule
// (Direct calls with string literals - these are allowed)

$result15 = compact('name', 'age'); // Direct call - OK
$result16 = extract($data); // Direct call - OK
$result17 = func_get_args(); // Direct call - OK

// Case 5: Dynamic calls to non-scope-introspection functions
// These should NOT trigger the rule

$func9 = 'strtolower';
$result18 = $func9('HELLO'); // Dynamic call to non-scope function - OK

$result19 = array_map('strtolower', ['HELLO', 'WORLD']); // array_map with non-scope function - OK

// Case 6: Variables that don't contain scope introspection function names
// These should NOT trigger the rule

$func10 = 'my_custom_function';
$result20 = $func10(); // Dynamic call to custom function - OK

$callback5 = 'array_merge';
$result21 = array_map($callback5, [['a'], ['b']]); // array_map with non-scope function - OK

// Case 7: More complex callback scenarios
$callbacks = ['compact', 'extract', 'parse_str'];
foreach ($callbacks as $cb) {
    $result22 = call_user_func($cb, 'test=data'); // Dynamic call in loop - should trigger
}

// Case 8: Nested function calls
$nestedFunc = 'compact';
$result23 = array_map($nestedFunc, [array_keys($data)]); // Nested dynamic call - should trigger

// Case 9: Function name with namespace
$namespacedFunc = '\\compact';
$result24 = $namespacedFunc('name'); // Namespaced dynamic call - should trigger

$namespacedFunc2 = 'compact';
$result25 = $namespacedFunc2('name'); // Non-namespaced dynamic call - should trigger