<?php
declare(strict_types=1);

// Test cases for AmbiguousMethodsCallsInArrayMappingRule

class TestObject {
    public function getValue(): int {
        return 42;
    }
    
    public function getName(): string {
        return "test";
    }
    
    public function getData(): array {
        return ["key" => "value"];
    }
}

function getRandomNumber(): int {
    return rand(1, 100);
}

function processValue($value): string {
    return strtoupper($value);
}

$objects = [new TestObject(), new TestObject(), new TestObject()];
$results = [];

// Case 1: Duplicated method call in foreach loop (should trigger error)
foreach ($objects as $index => $obj) {
    $results[$obj->getValue()] = $obj->getValue() * 2; // getValue() called twice - should trigger
}

// Case 2: Duplicated function call in for loop (should trigger error)
$data = [];
for ($i = 0; $i < 5; $i++) {
    $data[getRandomNumber()] = getRandomNumber() + 10; // getRandomNumber() called twice - should trigger
}

// Case 3: Duplicated method call with complex array access (should trigger error)
$matrix = [];
foreach ($objects as $key => $obj) {
    $matrix[$obj->getName()][$obj->getValue()] = $obj->getName() . "_processed"; // getName() called twice - should trigger
}

// Case 4: No duplication - different methods (should NOT trigger)
foreach ($objects as $index => $obj) {
    $results[$obj->getValue()] = $obj->getName(); // Different methods - no error
}

// Case 5: No duplication - same method but different objects (should NOT trigger)
$obj1 = new TestObject();
$obj2 = new TestObject();
foreach (range(1, 3) as $i) {
    $data[$obj1->getValue()] = $obj2->getValue(); // Same method but different objects - no error
}

// Case 6: Duplicated function call with different functions (should NOT trigger)
foreach (range(1, 5) as $i) {
    $data[getRandomNumber()] = processValue("test"); // Different functions - no error
}

// Case 7: Complex case with nested method calls (should trigger error)
$complexResults = [];
foreach ($objects as $obj) {
    $complexResults[$obj->getData()["key"]] = $obj->getData()["key"] . "_suffix"; // getData() called twice - should trigger
}

// Case 8: Method call only on one side (should NOT trigger)
foreach ($objects as $index => $obj) {
    $results[$index] = $obj->getValue(); // Only one method call - no error
}

// Case 9: Same method name but different types (should NOT trigger - different objects)
class AnotherClass {
    public function getValue(): string {
        return "different";
    }
}

$obj = new TestObject();
$another = new AnotherClass();
foreach (range(1, 3) as $i) {
    $data[$obj->getValue()] = $another->getValue(); // Same method name but different classes - no error
}

// Case 10: Multiple duplicated calls in nested loops
foreach ($objects as $outerObj) {
    foreach ($objects as $innerObj) {
        $matrix[$outerObj->getValue()][$innerObj->getValue()] = $outerObj->getValue() + $innerObj->getValue(); // Multiple duplications
    }
}