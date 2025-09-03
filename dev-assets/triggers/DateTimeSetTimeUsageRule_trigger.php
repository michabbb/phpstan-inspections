<?php declare(strict_types=1);

// Positive case: DateTime::setTime with 4 arguments (including microseconds) - should trigger error
$dt = new DateTime();
$result = $dt->setTime(10, 30, 45, 123456); // microseconds parameter

// Negative case: DateTime::setTime with 3 arguments - should not trigger
$result2 = $dt->setTime(10, 30, 45);

// Positive case: date_time_set function with 5 arguments - should trigger error
$result3 = date_time_set($dt, 10, 30, 45, 123456);

// Negative case: date_time_set function with 4 arguments - should not trigger
$result4 = date_time_set($dt, 10, 30, 45);