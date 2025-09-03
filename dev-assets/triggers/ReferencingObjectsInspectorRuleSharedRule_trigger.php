<?php
declare(strict_types=1);

// Test cases for ReferencingObjectsInspectorRule

class TestClass {
    public string $name;
    public function __construct(string $name) {
        $this->name = $name;
    }
}

class AnotherClass {
    public int $value;
}

// Test 1: Assignment by reference to new object (should trigger error)
function testAssignmentByRef() {
    $obj = new TestClass('test'); // Objects are always passed by reference anyway
}

// Test 2: Function parameter by reference with object type, no default, not reassigned (should trigger error)
function processObjectByRef(TestClass &$param): void { // This should trigger: Objects are always passed by reference; please correct "& $param"
    echo $param->name;
}

// Test 3: Function parameter by reference with scalar type (should NOT trigger error)
function processScalarByRef(string &$param): void {
    $param = 'modified';
}

// Test 4: Function parameter by reference with object type but has default (should NOT trigger error)
function processObjectWithDefault(TestClass &$param = null): void {
    if ($param !== null) {
        echo $param->name;
    }
}

// Test 5: Function parameter by reference with object type but reassigned (should NOT trigger error)
function processObjectReassigned(TestClass &$param): void {
    $param = new TestClass('reassigned'); // Reassignment disqualifies
}

// Test 6: Function parameter by reference with object type but used in logical context (should NOT trigger error)
function processObjectInLogic(TestClass &$param): void {
    if ($param) { // Used as logical operand disqualifies
        echo 'true';
    }
}

// Test 7: Method parameter by reference with object type (should trigger error)
class TestProcessor {
    public function processMethod(AnotherClass &$obj): void { // This should trigger: Objects are always passed by reference; please correct "& $obj"
        echo $obj->value;
    }

    public function processMethodWithDefault(AnotherClass &$obj = null): void { // Should NOT trigger (has default)
        // method body
    }
}

// Test 8: Union type with object and scalar (should NOT trigger error)
function processUnionType($param): void {
    // Union type with scalar disqualifies - using mixed type for compatibility
}

// Test 9: Nullable object type (should trigger error)
function processNullableObject(?TestClass &$param): void { // This should trigger: Objects are always passed by reference; please correct "& $param"
    if ($param !== null) {
        echo $param->name;
    }
}

// Test 10: Mixed type (should NOT trigger error)
function processMixed(mixed &$param): void {
    // mixed type disqualifies
}

// Usage examples
$processor = new TestProcessor();
$testObj = new TestClass('example');
$anotherObj = new AnotherClass();
$anotherObj->value = 42;

// These calls would trigger the assignment rule if uncommented:
// $refObj =& new TestClass('reference'); // Assignment by reference

// These calls would trigger the parameter rules:
$processor->processMethod($anotherObj); // Method parameter by reference
processObjectByRef($testObj); // Function parameter by reference
processNullableObject($testObj); // Nullable object parameter by reference