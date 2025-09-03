<?php
declare(strict_types=1);

// Positive cases - should trigger errors

// Case 1: Missing unset after foreach with reference
$array = ['a', 'b', 'c'];
foreach ($array as &$value) {
    $value = strtoupper($value);
}
// Missing unset($value); - should trigger MISSING_UNSET error

echo $value; // This could cause side effects

// Case 2: Unnecessary unset for non-reference variable
$array2 = ['x', 'y', 'z'];
foreach ($array2 as $val) {
    echo $val;
}
unset($val); // Unnecessary unset - should trigger UNNECESSARY_UNSET error

// Case 3: Array assignment that could benefit from reference
$array3 = ['key1' => 'value1', 'key2' => 'value2'];
foreach ($array3 as $key => $value) {
    $array3[$key] = strtoupper($value); // Should suggest using reference
}