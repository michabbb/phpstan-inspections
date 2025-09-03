<?php declare(strict_types=1);

// Test cases for InstanceofCanBeUsedRule
// This rule detects cases where 'instanceof' operator can be used instead of function calls

class TestClass {}
class ParentClass {}
class ChildClass extends ParentClass {}

interface TestInterface {}
class ImplementingClass implements TestInterface {}

function testGetClassPatterns($obj): void {
    // Should trigger: get_class($obj) == 'TestClass' can be replaced with $obj instanceof TestClass
    if (get_class($obj) == 'TestClass') {
        echo "Object is TestClass";
    }

    // Should trigger: get_class($obj) === 'TestClass' can be replaced with $obj instanceof TestClass
    if (get_class($obj) === 'TestClass') {
        echo "Object is exactly TestClass";
    }

    // Should trigger: 'TestClass' == get_class($obj) can be replaced with $obj instanceof TestClass
    if ('TestClass' == get_class($obj)) {
        echo "Object is TestClass (reversed)";
    }

    // Should trigger: get_class($obj) != 'TestClass' can be replaced with !($obj instanceof TestClass)
    if (get_class($obj) != 'TestClass') {
        echo "Object is not TestClass";
    }

    // Should trigger: get_class($obj) !== 'TestClass' can be replaced with !($obj instanceof TestClass)
    if (get_class($obj) !== 'TestClass') {
        echo "Object is not exactly TestClass";
    }
}

function testGetParentClassPatterns($obj): void {
    // Should trigger: get_parent_class($obj) == 'ParentClass' can be replaced with $obj instanceof ParentClass
    if (get_parent_class($obj) == 'ParentClass') {
        echo "Object's parent is ParentClass";
    }

    // Should trigger: get_parent_class($obj) === 'ParentClass' can be replaced with $obj instanceof ParentClass
    if (get_parent_class($obj) === 'ParentClass') {
        echo "Object's parent is exactly ParentClass";
    }
}

function testIsAPatterns($obj): void {
    // Should trigger: is_a($obj, 'TestClass') can be replaced with $obj instanceof TestClass
    if (is_a($obj, 'TestClass')) {
        echo "Object is a TestClass";
    }

    // Should trigger: is_a($obj, 'TestInterface') can be replaced with $obj instanceof TestInterface
    if (is_a($obj, 'TestInterface')) {
        echo "Object implements TestInterface";
    }

    // Should trigger: is_a($obj, 'TestClass', false) can be replaced with $obj instanceof TestClass
    if (is_a($obj, 'TestClass', false)) {
        echo "Object is a TestClass (allow_string=false)";
    }

    // Should NOT trigger: is_a($obj, 'TestClass', true) - allow_string is true
    if (is_a($obj, 'TestClass', true)) {
        echo "Object is a TestClass (allow_string=true)";
    }
}

function testIsSubclassOfPatterns($obj): void {
    // Should trigger: is_subclass_of($obj, 'ParentClass') can be replaced with $obj instanceof ParentClass
    if (is_subclass_of($obj, 'ParentClass')) {
        echo "Object is subclass of ParentClass";
    }

    // Should trigger: is_subclass_of($obj, 'TestInterface') can be replaced with $obj instanceof TestInterface
    if (is_subclass_of($obj, 'TestInterface')) {
        echo "Object is subclass of TestInterface";
    }
}

function testInArrayClassImplementsPatterns($obj): void {
    // Should trigger: in_array('TestInterface', class_implements($obj)) can be replaced with $obj instanceof TestInterface
    if (in_array('TestInterface', class_implements($obj))) {
        echo "Object implements TestInterface";
    }

    // Should trigger: in_array('AnotherInterface', class_implements($obj)) can be replaced with $obj instanceof AnotherInterface
    if (in_array('AnotherInterface', class_implements($obj))) {
        echo "Object implements AnotherInterface";
    }
}

function testInArrayClassParentsPatterns($obj): void {
    // Should trigger: in_array('ParentClass', class_parents($obj)) can be replaced with $obj instanceof ParentClass
    if (in_array('ParentClass', class_parents($obj))) {
        echo "Object extends ParentClass";
    }

    // Should trigger: in_array('GrandParentClass', class_parents($obj)) can be replaced with $obj instanceof GrandParentClass
    if (in_array('GrandParentClass', class_parents($obj))) {
        echo "Object extends GrandParentClass";
    }
}

// Test with actual objects
$testObj = new ChildClass();
$implementingObj = new ImplementingClass();

// These should trigger the rule
testGetClassPatterns($testObj);
testGetParentClassPatterns($testObj);
testIsAPatterns($testObj);
testIsSubclassOfPatterns($testObj);
testInArrayClassImplementsPatterns($implementingObj);
testInArrayClassParentsPatterns($testObj);