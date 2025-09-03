<?php declare(strict_types=1);

/**
 * Trigger script for StrtotimeUsageRule testing
 * This file contains test cases that should trigger the rule
 */

// Case 1: strtotime("now") should be replaced with time()
$bad1 = strtotime("now"); // Should trigger: 'time()' should be used instead (2x faster).
$bad2 = strtotime("NOW"); // Should trigger: case insensitive
$bad3 = strtotime('now'); // Should trigger: single quotes too

// Case 2: strtotime(..., time()) can be simplified
$bad4 = strtotime("+1 day", time()); // Should trigger: 'time()' is default valued already, it can safely be removed.
$bad5 = strtotime("-1 week", time()); // Should trigger
$bad6 = strtotime("2023-01-01", time()); // Should trigger

// Valid uses that should NOT trigger
$good1 = time(); // Already using time()
$good2 = strtotime("+1 day"); // No second argument, uses default time()
$good3 = strtotime("2023-01-01"); // Valid date string
$good4 = strtotime("+1 day", 1234567890); // Valid timestamp
$good5 = strtotime("tomorrow"); // Valid relative date

// Edge cases
$edge1 = strtotime("now", 1234567890); // Second arg is not time(), should not trigger
$edge2 = strtotime("yesterday", time()); // Should trigger second case
$edge3 = strtotime(); // No args, should not trigger (handled by arg count check)