<?php

declare(strict_types=1);

// Positive cases - should trigger the rule

// Case 1: Basic call_user_func_array with string literal and variable
$args = ['hello', 'world'];
$result = call_user_func_array('implode', $args);

// Case 2: call_user_func_array with property access
class TestClass {
    public array $args = ['a', 'b', 'c'];

    public function test(): void {
        $result = call_user_func_array('array_merge', $this->args);
    }
}

// Case 3: call_user_func_array with array creation
$result = call_user_func_array('sprintf', ['%s %s', 'hello', 'world']);

// Case 4: call_user_func_array with function call
$result = call_user_func_array('str_replace', getArgs());