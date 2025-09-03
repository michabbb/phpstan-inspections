<?php declare(strict_types=1);

// Test file for UnsupportedEmptyListAssignmentsRule
// This rule detects empty list/array assignments in foreach loops

$data = [1, 2, 3];
$assocData = ['a' => 1, 'b' => 2, 'c' => 3];

// Valid foreach loops that should NOT trigger the rule
foreach ($data as $key => $value) {
    echo $key . ': ' . $value . "\n";
}

foreach ($assocData as $key => $value) {
    echo $key . ' => ' . $value . "\n";
}

// Valid destructuring that should NOT trigger the rule
foreach ($data as list($a)) {
    echo 'Value: ' . $a . "\n";
}

foreach ($data as [$a]) {
    echo 'Value: ' . $a . "\n";
}

// Note: PHP doesn't support destructuring keys in foreach loops
// These would be syntax errors if attempted:
// foreach ($assocData as list($k) => $v) { ... }
// foreach ($assocData as [$k] => $v) { ... }

// Test with multidimensional arrays
$matrix = [[1, 2], [3, 4]];
foreach ($matrix as list($a, $b)) {
    echo $a . ', ' . $b . "\n";
}

foreach ($matrix as [$a, $b]) {
    echo $a . ', ' . $b . "\n";
}
