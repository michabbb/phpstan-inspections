<?php declare(strict_types=1);

// Positive cases - should trigger the rule

// Case 1: Variable that could be null
function testNullableVariable(?string $obj = null) {
    return get_class($obj); // Should trigger: argument could be null
}

// Case 2: Union type with null
function testUnionType(string|null $obj) {
    return get_class($obj); // Should trigger: argument could be null
}

// Case 3: Variable from nullable parameter
function testFromNullableParam(?stdClass $param = null) {
    $obj = $param;
    return get_class($obj); // Should trigger: $obj could be null
}

// Case 4: Mixed type (could include null)
function testMixedType(mixed $obj) {
    return get_class($obj); // Should trigger: mixed could be null
}

// Negative cases - should NOT trigger the rule

// Case 1: Non-nullable type
function testNonNullableType(string $obj) {
    return get_class($obj); // Should NOT trigger: string cannot be null
}

// Case 2: Object type
function testObjectType(stdClass $obj) {
    return get_class($obj); // Should NOT trigger: object cannot be null
}

// Case 3: $this (always non-null in instance methods)
class TestClass {
    public function testThis() {
        return get_class($this); // Should NOT trigger: $this is never null
    }
}

// Case 4: No arguments (get_class() without arguments)
function testNoArgs() {
    return get_class(); // Should NOT trigger: no arguments, gets current class
}

// Case 5: Literal values
function testLiterals() {
    return get_class(new stdClass()); // Should NOT trigger: new object is never null
}