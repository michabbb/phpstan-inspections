<?php

// Positive cases - should trigger the TernaryOperatorSimplifyRule

// Case 1: Basic comparison with true/false branches
$number = 5;
$variable1 = $number > 0 ? true : false; // Should trigger: Can be simplified to '$number > 0'

// Case 2: Inverted logic with false/true branches
$variable2 = $number > 0 ? false : true; // Should trigger: Can be simplified to '!($number > 0)'

// Case 3: Different comparison operators
$value = 10;
$variable3 = $value === 5 ? true : false; // Should trigger: Can be simplified to '$value === 5'
$variable4 = $value !== 5 ? false : true; // Should trigger: Can be simplified to '!($value !== 5)' or '$value === 5'

// Case 4: Greater than/less than operators
$variable5 = $number >= 10 ? true : false; // Should trigger: Can be simplified to '$number >= 10'
$variable6 = $number < 10 ? false : true; // Should trigger: Can be simplified to '!($number < 10)' or '$number >= 10'

// Case 5: Logical operators (need parentheses and casting)
$flag1 = true;
$flag2 = false;
$variable7 = $flag1 && $flag2 ? true : false; // Should trigger: Can be simplified to '(bool)($flag1 && $flag2)'
$variable8 = $flag1 || $flag2 ? false : true; // Should trigger: Can be simplified to '!($flag1 || $flag2)'

// Case 6: With parentheses around condition
$variable9 = ($number > 0) ? true : false; // Should trigger: Can be simplified to '$number > 0'

// Case 7: With property access
class TestClass {
    public int $value = 5;
}
$instance = new TestClass();
$variable10 = $instance->value > 0 ? true : false; // Should trigger: Can be simplified to '$instance->value > 0'

// Case 8: With method calls
class Calculator {
    public function isPositive(int $num): bool {
        return $num > 0;
    }
}
$calc = new Calculator();
$variable11 = $calc->isPositive(5) ? true : false; // Should trigger: Can be simplified to '$calc->isPositive(5)'

// Negative cases - should NOT trigger the rule

// Case 1: Non-boolean constants in branches
$variable12 = $number > 0 ? 1 : 0; // Should NOT trigger (not boolean constants)
$variable13 = $number > 0 ? 'yes' : 'no'; // Should NOT trigger (not boolean constants)

// Case 2: Non-binary condition
$boolVar = true;
$variable14 = $boolVar ? true : false; // Should NOT trigger (condition is not a binary operation)

// Case 3: Complex expressions in branches
$variable15 = $number > 0 ? ($number * 2) : ($number / 2); // Should NOT trigger (not boolean constants)

// Case 4: Already simplified
$variable16 = $number > 0; // Should NOT trigger (already simplified)

// Case 5: Ternary with different logic
$variable17 = $number > 0 ? false : false; // Should NOT trigger (both branches false, but not a simplification case)
$variable18 = $number > 0 ? true : true; // Should NOT trigger (both branches true, but not a simplification case)