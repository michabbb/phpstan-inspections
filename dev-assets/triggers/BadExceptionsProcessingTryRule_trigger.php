<?php declare(strict_types=1);

// This file demonstrates the BadExceptionsProcessingTryRule
// It contains try blocks that should trigger the rule

// Positive case 1: Try block with more than 3 statements (should trigger)
try {
    $file = fopen('test.txt', 'r');
    $content = fread($file, 1024);
    echo $content;
    fclose($file);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

// Positive case 2: Try block with exactly 4 statements (should trigger)
try {
    $data = [];
    $data[] = 'item1';
    $data[] = 'item2';
    $data[] = 'item3';
    $data[] = 'item4';
} catch (Exception $e) {
    // Handle error
}

// Negative case: Try block with 3 or fewer statements (should NOT trigger)
try {
    $result = doSomething();
    echo $result;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

// Negative case: Try block with 2 statements (should NOT trigger)
try {
    $value = getValue();
    processValue($value);
} catch (Exception $e) {
    logError($e);
}