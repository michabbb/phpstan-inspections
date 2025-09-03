<?php declare(strict_types=1);

// Positive cases - should trigger the OnlyWritesOnParameterRule

// Function parameter that is reassigned but never used
function unusedParameterReassignment(string $param): void {
    $param = 'new value'; // Parameter is written to but never read
}

// Function parameter that is modified but never used
function unusedParameterModification(int $param): void {
    $param += 5; // Parameter is modified but never read
}

// Local variable that is assigned but never read
function unusedLocalVariable(): void {
    $localVar = 'assigned'; // Variable is written to but never read
    $anotherVar = 42;
    $anotherVar = 100; // Reassigned but never read
}

// Method parameter that is reassigned but never used
class TestClass {
    public function unusedMethodParameter(string $param): void {
        $param = 'modified'; // Parameter is written to but never read
    }

    public function unusedLocalVariableInMethod(): void {
        $methodVar = 'assigned'; // Variable is written to but never read
        $anotherMethodVar = 42;
        $anotherMethodVar += 10; // Modified but never read
    }

    // Array access assignment on parameter
    public function unusedArrayParameter(array $param): void {
        $param['key'] = 'value'; // Parameter is written to but never read
    }
}

// Negative cases - should NOT trigger the rule

// Parameter that is used after reassignment
function usedParameterAfterReassignment(string $param): string {
    $param = 'new value'; // Parameter is written to
    return $param; // And then read
}

// Parameter that is used in condition
function usedParameterInCondition(string $param): bool {
    $param = 'test'; // Parameter is written to
    return $param === 'test'; // And then read
}

// Local variable that is used after assignment
function usedLocalVariable(): string {
    $localVar = 'assigned'; // Variable is written to
    return $localVar; // And then read
}

// Pass-by-reference parameter (should be skipped)
function referenceParameter(int &$param): void {
    $param = 100; // This is a write, but rule should skip reference parameters
}

// Object parameter (should be skipped by rule)
function objectParameter(\stdClass $param): void {
    $param->property = 'value'; // This is a write, but rule should skip objects
}

// Parameter used in array access
function parameterUsedInArrayAccess(array $param): mixed {
    $param['key'] = 'value'; // Write
    return $param['key']; // Read
}

// Local variable used in array access
function localVariableUsedInArrayAccess(): mixed {
    $localVar = ['key' => 'value']; // Write
    return $localVar['key']; // Read
}