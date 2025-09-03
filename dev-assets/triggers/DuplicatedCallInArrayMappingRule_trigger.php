<?php

declare(strict_types=1);

// Test file for DuplicatedCallInArrayMappingRule
// This rule detects duplicated method/function calls in array mapping assignments within loops

class TestObject {
    public function id(): int {
        return 42;
    }

    public function name(): string {
        return 'test';
    }

    public static function getType(): string {
        return 'object';
    }
}

function getId(): int {
    return 123;
}

function getName(): string {
    return 'function';
}

// Positive cases - should trigger the rule (duplicated calls in array mapping within loops)

$items = [new TestObject(), new TestObject()];
$result = [];

// Method call duplication
foreach ($items as $item) {
    $result[$item->id()] = $item->id();  // Should trigger: duplicated method call
}

// Static method call duplication
foreach ($items as $item) {
    $result[$item::getType()] = $item::getType();  // Should trigger: duplicated static call
}

// Function call duplication
foreach ($items as $item) {
    $result[getId()] = getId();  // Should trigger: duplicated function call
}

// Mixed method and function duplication
foreach ($items as $item) {
    $result[$item->id()] = getId();  // Should NOT trigger: different calls
    $result[getId()] = $item->id();  // Should NOT trigger: different calls
}

// More complex cases
foreach ($items as $item) {
    $result[$item->name()] = $item->name();  // Should trigger: duplicated method call
}

// Nested array access with duplication
foreach ($items as $item) {
    $nested[$item->id()]['value'] = $item->id();  // Should trigger: duplicated method call
}

// Negative cases - should NOT trigger the rule

// No loop - should not trigger
$result[$item->id()] = $item->id();

// Different method calls - should not trigger
foreach ($items as $item) {
    $result[$item->id()] = $item->name();  // Different methods
}

// Variable assignment - should not trigger
foreach ($items as $item) {
    $id = $item->id();
    $result[$id] = $id;  // No duplication in calls
}

// No array assignment - should not trigger
foreach ($items as $item) {
    $value = $item->id();
    $other = $item->id();  // Not an array mapping assignment
}

// Different array keys - should not trigger
foreach ($items as $item) {
    $result[$item->id()] = getId();  // Different calls
    $result[getId()] = $item->id();  // Different calls
}