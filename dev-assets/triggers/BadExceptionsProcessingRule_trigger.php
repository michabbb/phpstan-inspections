<?php declare(strict_types=1);

// This file should trigger the BadExceptionsProcessingRule
// Contains test cases for both try block and catch block violations

// Case 1: Try block with more than 3 statements (should trigger)
try {
    $file = fopen('test.txt', 'r');
    $content = fread($file, 1024);
    echo $content;
    fclose($file);
    $processed = strtoupper($content); // This makes it 5 statements
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}

// Case 2: Empty catch block (should trigger "fail silently")
try {
    riskyOperation();
} catch (Exception $e) {
    // Empty catch block - silently ignores the exception
}

// Case 3: Catch block with unused exception variable (should trigger "chained exception")
try {
    anotherRiskyOperation();
} catch (Exception $unusedException) {
    // Exception variable is not used in the catch block
    echo "An error occurred";
    logToFile("Error happened");
}

// Case 4: Valid catch block (should NOT trigger)
try {
    databaseQuery();
} catch (Exception $e) {
    echo 'Database error: ' . $e->getMessage();
    logError($e);
}

// Case 5: Valid try block with 3 or fewer statements (should NOT trigger)
try {
    $result = doSomething();
    echo $result;
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}