<?php

declare(strict_types=1);

// Test file for SuspiciousFunctionCallsRule

// Positive cases - should trigger the rule
$str     = 'hello';
$result1 = strcmp($str, $str);  // Should trigger: comparing same variable with itself
$result2 = strcasecmp('test', 'test');  // Should trigger: comparing same literal string
$result3 = strnatcmp($str, $str);  // Should trigger: comparing same variable
$result4 = hash_equals($str, $str);  // Should trigger: comparing same variable

// More complex cases that should trigger
$obj     = (object) ['prop' => 'value'];
$result5 = strcmp($obj->prop, $obj->prop);  // Should trigger: same property access

$arr     = ['key' => 'value'];
$result6 = strncmp($arr['key'], $arr['key'], 5);  // Should trigger: same array access

// Negative cases - should NOT trigger the rule
$str1    = 'hello';
$str2    = 'world';
$result7 = strcmp($str1, $str2);  // Should NOT trigger: different variables
$result8 = strcasecmp('hello', 'world');  // Should NOT trigger: different literals
$result9 = strnatcmp($str1, 'hello');  // Should NOT trigger: variable vs literal

// Edge cases that should NOT trigger
$result10 = strcmp('hello');  // Should NOT trigger: insufficient arguments
$result11 = strcmp();  // Should NOT trigger: no arguments

// Function call comparisons that should trigger
function getValue(): string
{
    return 'test';
}

$result12 = strcmp(getValue(), getValue());  // Should trigger: same function call

// Constants that should trigger
define('MY_CONSTANT', 'test');
$result13 = strcmp(MY_CONSTANT, MY_CONSTANT);  // Should trigger: same constant
