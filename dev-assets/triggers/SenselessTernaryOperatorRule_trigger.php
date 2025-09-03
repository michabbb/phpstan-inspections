<?php

// Positive cases - should trigger the rule

// Case 1: $a === $b ? $a : $b should be simplified to $b
$a = 1;
$b = 2;
$result1 = $a === $b ? $a : $b; // Should trigger: Can be replaced with '$b'

// Case 2: $x !== $y ? $y : $x should be simplified to $x
$x = 'hello';
$y = 'world';
$result2 = $x !== $y ? $y : $x; // Should trigger: Can be replaced with '$x'

// Case 3: With property access
class TestClass {
    public string $prop1 = 'value1';
    public string $prop2 = 'value2';
}

$instance = new TestClass();
$result3 = $instance->prop1 === $instance->prop2 ? $instance->prop1 : $instance->prop2; // Should trigger

// Case 4: With array access
$array1 = ['key' => 'value1'];
$array2 = ['key' => 'value2'];
$result4 = $array1['key'] === $array2['key'] ? $array1['key'] : $array2['key']; // Should trigger

// Negative cases - should NOT trigger the rule

// Case 1: Different operands
$result5 = $a === $b ? $b : $a; // Should NOT trigger (already correct)

// Case 2: Different operator
$result6 = $a == $b ? $a : $b; // Should NOT trigger (== instead of ===)

// Case 3: Different values in branches
$result7 = $a === $b ? $x : $y; // Should NOT trigger (different variables)

// Case 4: Non-binary condition
$result8 = $a ? $a : $b; // Should NOT trigger (not a comparison)

// Case 5: Complex expressions
$result9 = ($a + 1) === ($b + 1) ? ($a + 1) : ($b + 1); // Should NOT trigger (complex expressions)