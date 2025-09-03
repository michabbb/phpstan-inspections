<?php
declare(strict_types=1);

// Negative cases - should NOT trigger errors

// Case 1: Proper unset after foreach with reference
$array = ['a', 'b', 'c'];
foreach ($array as &$value) {
    $value = strtoupper($value);
}
unset($value); // Proper cleanup - should NOT trigger error

// Case 2: Foreach with reference ending in return
$array2 = ['x', 'y', 'z'];
foreach ($array2 as &$val) {
    if ($val === 'y') {
        return; // Control flow end - should NOT trigger error
    }
    $val = strtoupper($val);
}

// Case 3: Foreach with reference ending in throw
$array3 = ['a', 'b', 'c'];
foreach ($array3 as &$item) {
    if ($item === 'invalid') {
        throw new Exception('Invalid item'); // Control flow end - should NOT trigger error
    }
    $item = 'processed';
}

// Case 4: Non-reference foreach without unset
$array4 = ['p', 'q', 'r'];
foreach ($array4 as $element) {
    echo $element;
}
// No unset needed for non-reference - should NOT trigger error

// Case 5: Array assignment with reference already used
$array5 = ['key1' => 'value1', 'key2' => 'value2'];
foreach ($array5 as &$value) {
    $value = strtoupper($value); // Already using reference - should NOT trigger suggestion
}
unset($value);