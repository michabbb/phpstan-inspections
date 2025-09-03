<?php declare(strict_types=1);

/**
 * Trigger script for SuspiciousReturnRule testing
 * This file contains various test cases for the rule
 */

// Case 1: Suspicious return in finally that voids return from try
function suspiciousReturnVoidsTryReturn(): int
{
    try {
        return 42; // This return will be voided by finally
    } finally {
        return 0; // ERROR: This voids the try return
    }
}

// Case 2: Suspicious return in finally that voids throw from try
function suspiciousReturnVoidsTryThrow(): void
{
    try {
        throw new Exception('Test exception'); // This throw will be voided
    } finally {
        return; // ERROR: This voids the try throw
    }
}

// Case 3: Suspicious return in finally that voids return from catch
function suspiciousReturnVoidsCatchReturn(): int
{
    try {
        throw new Exception('Test');
    } catch (Exception $e) {
        return 100; // This return will be voided by finally
    } finally {
        return 0; // ERROR: This voids the catch return
    }
}

// Case 4: Non-suspicious return in finally (no return/throw in try or catch)
function nonSuspiciousReturnInFinally(): int
{
    try {
        $value = 42;
        // No return or throw here
    } finally {
        return 0; // OK: No return/throw to void
    }
}

// Case 5: Return in try block (should not trigger rule)
function returnInTryOnly(): int
{
    try {
        return 42; // OK: This is fine
    } finally {
        // No return here
    }
}

// Case 6: Return in catch block (should not trigger rule)
function returnInCatchOnly(): int
{
    try {
        throw new Exception('Test');
    } catch (Exception $e) {
        return 42; // OK: This is fine
    } finally {
        // No return here
    }
}

// Case 7: Throw in try, no return in finally (should not trigger)
function throwInTryNoReturnInFinally(): void
{
    try {
        throw new Exception('Test');
    } finally {
        // No return here, so no voiding
    }
}

// Case 8: Complex nested case
function nestedSuspiciousReturn(): int
{
    try {
        if (rand(0, 1)) {
            return 1;
        } else {
            throw new Exception('Random');
        }
    } finally {
        return 0; // ERROR: Voids both return and throw from try
    }
}