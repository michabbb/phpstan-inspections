<?php

// Positive cases - should trigger the NoNestedTernaryRule

// Case 1: Nested ternary in condition
$a = 1;
$b = 2;
$c = 3;
$result1 = ($a > $b ? $a : $b) > $c ? 'greater' : 'smaller'; // Should trigger: nested ternary in condition

// Case 2: Nested ternary in true branch
$result2 = $a > $b ? ($b > $c ? 'b>c' : 'b<=c') : 'a<=b'; // Should trigger: nested ternary in true branch

// Case 3: Nested ternary in false branch
$result3 = $a > $b ? 'a>b' : ($b > $c ? 'b>c' : 'b<=c'); // Should trigger: nested ternary in false branch

// Case 4: Multiple levels of nesting
$result4 = $a > 0 ? ($b > 0 ? ($c > 0 ? 'all positive' : 'c negative') : 'b negative') : 'a negative'; // Should trigger: deeply nested

// Case 5: Nested ternary with parentheses (should still trigger)
$result5 = ($a > $b ? $a : $b) > $c ? 'greater' : 'smaller'; // Should trigger: nested even with parens

// Negative cases - should NOT trigger the rule

// Case 1: Simple ternary (no nesting)
$result6 = $a > $b ? 'a>b' : 'a<=b'; // Should NOT trigger

// Case 2: Multiple separate ternary expressions
$result7 = $a > $b ? 'a>b' : 'a<=b';
$result8 = $c > 0 ? 'positive' : 'negative'; // Should NOT trigger

// Case 3: Ternary with function calls (not nested ternary)
$result9 = $a > $b ? strtoupper('hello') : strtoupper('world'); // Should NOT trigger

// Case 4: Ternary with array access (not nested ternary)
$array = ['key1' => 'value1', 'key2' => 'value2'];
$result10 = $a > $b ? $array['key1'] : $array['key2']; // Should NOT trigger