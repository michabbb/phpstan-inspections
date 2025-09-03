<?php declare(strict_types=1);

// Positive cases - should trigger errors

// Case 1: mktime() without arguments - should suggest time()
$timestamp1 = mktime();

// Case 2: gmmktime() without arguments - should suggest time()
$timestamp2 = gmmktime();

// Case 3: mktime() with 7 arguments - deprecated is_dst parameter
$timestamp3 = mktime(12, 30, 45, 6, 15, 2023, 1);

// Case 4: gmmktime() with 7 arguments - deprecated is_dst parameter
$timestamp4 = gmmktime(12, 30, 45, 6, 15, 2023, 0);

// Negative cases - should NOT trigger errors

// Case 5: mktime() with valid arguments (less than 7)
$timestamp5 = mktime(12, 30, 45, 6, 15, 2023);

// Case 6: gmmktime() with valid arguments (less than 7)
$timestamp6 = gmmktime(12, 30, 45, 6, 15, 2023);

// Case 7: time() function - should not trigger
$timestamp7 = time();

// Case 8: Other date functions - should not trigger
$date1 = date('Y-m-d');
$date2 = strtotime('now');