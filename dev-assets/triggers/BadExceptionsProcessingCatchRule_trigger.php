<?php declare(strict_types=1);

// This file demonstrates the BadExceptionsProcessingCatchRule
// It contains catch blocks that should trigger the rule

// Positive case 1: Empty catch block (should trigger "fail silently")
try {
    riskyOperation();
} catch (Exception $e) {
    // Empty catch block - silently ignores the exception
}

// Positive case 2: Catch block with statements but unused exception variable (should trigger "chained exception")
try {
    anotherRiskyOperation();
} catch (Exception $unusedException) {
    // Exception variable is not used in the catch block
    echo "An error occurred";
    logToFile("Error happened");
}

// Positive case 3: Another example of unused exception in catch with statements
try {
    fileOperation();
} catch (IOException $ex) {
    // Exception variable $ex is not used
    echo "File operation failed";
    return false;
}

// Negative case 1: Catch block that uses the exception variable (should NOT trigger)
try {
    databaseQuery();
} catch (Exception $e) {
    echo 'Database error: ' . $e->getMessage();
    logError($e);
}

// Negative case 2: Catch block with multiple statements using exception (should NOT trigger)
try {
    apiCall();
} catch (ApiException $e) {
    $errorMessage = 'API Error: ' . $e->getMessage();
    $this->logger->error($errorMessage);
    throw new CustomException($errorMessage, 0, $e);
}

// Negative case 3: Catch block that re-throws the exception (should NOT trigger)
try {
    processData();
} catch (ValidationException $e) {
    // Log the error
    $this->logger->warning('Validation failed: ' . $e->getMessage());
    // Re-throw with additional context
    throw $e;
}