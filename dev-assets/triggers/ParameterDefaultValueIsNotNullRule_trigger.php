<?php declare(strict_types=1);

// Positive cases - should trigger the rule

// Function with untyped parameter and non-null default
function exampleFunction($param = 'default') {
    return $param;
}

// Function with nullable typed parameter and non-null default
function exampleNullableFunction(?string $param = 'default') {
    return $param;
}

// Method with untyped parameter and non-null default
class ExampleClass {
    public function exampleMethod($param = 42) {
        return $param;
    }

    // Method with nullable typed parameter and non-null default
    public function exampleNullableMethod(?int $param = 0) {
        return $param;
    }
}

// Negative cases - should NOT trigger the rule

// Function with null default (correct)
function correctFunction($param = null) {
    return $param;
}

// Function with typed non-nullable parameter and non-null default (allowed)
function typedFunction(string $param = 'default') {
    return $param;
}

// Function with nullable type and null default (correct)
function correctNullableFunction(?string $param = null) {
    return $param;
}

// Method with null default (correct)
class CorrectClass {
    public function correctMethod($param = null) {
        return $param;
    }

    // Method with typed non-nullable parameter and non-null default (allowed)
    public function typedMethod(int $param = 42) {
        return $param;
    }
}