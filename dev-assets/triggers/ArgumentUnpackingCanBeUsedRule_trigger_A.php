<?php

declare(strict_types=1);

// Negative cases - should NOT trigger the rule

// Case 1: call_user_func_array with variable function name (not string literal)
$func = 'implode';
$args = ['hello', 'world'];
$result = call_user_func_array($func, $args);

// Case 2: call_user_func_array with wrong number of arguments
$result = call_user_func_array('implode');

// Case 3: call_user_func_array with 3 arguments
$result = call_user_func_array('implode', $args, 'extra');

// Case 4: Regular function call (not call_user_func_array)
$result = implode(' ', $args);

// Case 5: call_user_func_array with constant (not string literal)
define('MY_FUNC', 'implode');
$result = call_user_func_array(MY_FUNC, $args);

// Case 6: call_user_func_array with method call (not supported by this rule)
class TestClass {
    public function getArgs(): array {
        return ['hello', 'world'];
    }

    public function test(): void {
        $result = call_user_func_array('implode', $this->getArgs());
    }
}