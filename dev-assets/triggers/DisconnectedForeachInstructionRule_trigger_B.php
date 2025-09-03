<?php

// Positive test cases - these should trigger the DisconnectedForeachInstructionRule

$items = [1, 2, 3];
$result = [];
$externalVar = "test";

// Case 1: Echo statement using external variable
foreach ($items as $item) {
    echo $externalVar; // Should trigger - uses variable not from loop
    $result[] = $item * 2;
}

// Case 2: Function call using external variable
foreach ($items as $key => $value) {
    processData($externalVar); // Should trigger - uses variable not from loop
    $result[$key] = $value;
}

// Case 3: Variable assignment using external variable
foreach ($items as $item) {
    $temp = $externalVar . $item; // Should trigger - uses external variable
    $result[] = $item;
}

// Case 4: Complex expression using external variable
foreach ($items as $item) {
    $calculation = strlen($externalVar) + $item; // Should trigger - uses external variable
    $result[] = $calculation;
}

// Case 5: Static method call with external variable
foreach ($items as $item) {
    MyClass::process($externalVar); // Should trigger - uses external variable
    $result[] = $item;
}

function processData(string $data): void {}
class MyClass {
    public static function process(string $data): void {}
}