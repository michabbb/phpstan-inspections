<?php declare(strict_types=1);

// This file demonstrates the MkdirRaceConditionRule
// Run PHPStan on this file to see the rule in action

// POSITIVE CASES - These should trigger the rule

// Case 1: Direct mkdir() call without any checks
mkdir('/tmp/test1');

// Case 2: mkdir() in if statement without is_dir() check
if (mkdir('/tmp/test2')) {
    echo "Directory created";
}

// Case 3: mkdir() in && expression without is_dir() check
if (file_exists('/tmp/test3') && mkdir('/tmp/test3')) {
    echo "Directory created";
}

// Case 4: mkdir() in || expression without is_dir() check
if (!file_exists('/tmp/test4') || mkdir('/tmp/test4')) {
    echo "Directory created";
}

// Case 5: mkdir() with negated condition in if statement
if (!mkdir('/tmp/test5')) {
    echo "Failed to create directory";
}

// NEGATIVE CASES - These should NOT trigger the rule

// Case 1: Proper race condition protection (recommended pattern)
if (!is_dir('/tmp/safe1') && !mkdir('/tmp/safe1') && !is_dir('/tmp/safe1')) {
    throw new \RuntimeException('Directory "/tmp/safe1" was not created');
}

// Case 2: mkdir() with is_dir() check in if condition
if (!is_dir('/tmp/safe2')) {
    mkdir('/tmp/safe2');
}

// Case 3: mkdir() in && expression with is_dir() check
if (!is_dir('/tmp/safe3') && mkdir('/tmp/safe3')) {
    echo "Directory created";
}

// Case 4: mkdir() in || expression with is_dir() check
if (is_dir('/tmp/safe4') || mkdir('/tmp/safe4')) {
    echo "Directory exists or was created";
}

// Case 6: Non-mkdir function calls (should be ignored)
file_put_contents('/tmp/test.txt', 'content');
is_dir('/tmp/test');
unlink('/tmp/test.txt');

// Case 7: mkdir() in test file context (should be skipped)
function testMkdir(): void {
    mkdir('/tmp/test7');
}