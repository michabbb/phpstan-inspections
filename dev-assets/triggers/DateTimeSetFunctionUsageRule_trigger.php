<?php
declare(strict_types=1);

// Test cases for DateTimeSetFunctionUsageRule

$datetime = new DateTime();
$dateTimeImmutable = new DateTimeImmutable();

// Case 1: date_time_set with 5 arguments including microseconds (should trigger error)
$result1 = date_time_set($datetime, 14, 30, 45, 123456);
echo "Result 1: " . ($result1 ? "success" : "failed") . "\n";

// Case 2: Another date_time_set with microseconds (should trigger error)
$result2 = date_time_set($dateTimeImmutable, 9, 15, 20, 654321);
echo "Result 2: " . ($result2 ? "success" : "failed") . "\n";

// Case 3: date_time_set with variables as microseconds parameter (should trigger error)
$hour = 16;
$minute = 45;
$second = 30;
$microseconds = 999999;
$result3 = date_time_set($datetime, $hour, $minute, $second, $microseconds);
echo "Result 3: " . ($result3 ? "success" : "failed") . "\n";

// Case 4: date_time_set with expression as microseconds (should trigger error)
$result4 = date_time_set($datetime, 12, 0, 0, 500000 + 250000);
echo "Result 4: " . ($result4 ? "success" : "failed") . "\n";

// Case 5: Multiple date_time_set calls with microseconds (should trigger multiple errors)
$dt1 = new DateTime();
$dt2 = new DateTime();
date_time_set($dt1, 10, 30, 15, 123456);
date_time_set($dt2, 22, 45, 50, 789012);

// Case 6: date_time_set in conditional (should trigger error)
if (date_time_set($datetime, 8, 30, 0, 500000)) {
    echo "Time set successfully with microseconds\n";
} else {
    echo "Failed to set time with microseconds\n";
}

// Case 7: date_time_set with zero microseconds (should still trigger error)
$result7 = date_time_set($datetime, 15, 30, 45, 0);
echo "Result 7: " . ($result7 ? "success" : "failed") . "\n";

// Case 8: date_time_set with negative microseconds (should trigger error)
$result8 = date_time_set($datetime, 12, 0, 0, -100000);
echo "Result 8: " . ($result8 ? "success" : "failed") . "\n";

// Case 9: Proper usage with 4 arguments - should NOT trigger
$result9 = date_time_set($datetime, 14, 30, 45);
echo "Result 9 (proper): " . ($result9 ? "success" : "failed") . "\n";

// Case 10: date_time_set with 3 arguments - should NOT trigger
$result10 = date_time_set($datetime, 14, 30);
echo "Result 10 (3 args): " . ($result10 ? "success" : "failed") . "\n";

// Case 11: date_time_set with 6+ arguments - should NOT trigger (wrong argument count)
// This would be a syntax error, but for testing purposes:
// $result11 = date_time_set($datetime, 14, 30, 45, 123456, "extra");

// Case 12: Different function name - should NOT trigger
function custom_date_time_set($dt, $h, $m, $s, $ms) {
    // Custom implementation
    return true;
}
$custom_result = custom_date_time_set($datetime, 14, 30, 45, 123456);

// Case 13: date_time_set in function call (should trigger error)
function setTimeWithMicroseconds($dateTime, $microsec) {
    return date_time_set($dateTime, 12, 0, 0, $microsec);
}
$funcResult = setTimeWithMicroseconds($datetime, 750000);

// Case 14: date_time_set in array (should trigger error)
$operations = [
    'set_time' => date_time_set($datetime, 18, 30, 0, 123456),
    'other' => "value"
];

// Case 15: Chained usage (should trigger error)
$newDateTime = clone $datetime;
$chainResult = date_time_set($newDateTime, 20, 15, 30, 999999) ? "success" : "failed";
echo "Chain result: " . $chainResult . "\n";