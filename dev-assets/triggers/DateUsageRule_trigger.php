<?php declare(strict_types=1);

// Positive case: Should trigger the rule
$dateWithTime = date('Y-m-d H:i:s', time());

// Negative case 1: date() with one argument
$dateOnly = date('Y-m-d');

// Negative case 2: date() with two arguments, but second is not time()
$timestamp = 1678886400;
$dateWithTimestamp = date('Y-m-d H:i:s', $timestamp);

// Negative case 3: time() with arguments
$timeWithArg = time(true);
$dateWithTimeArg = date('Y-m-d H:i:s', $timeWithArg);

// Negative case 4: Another function call as second argument
function getTimestamp(): int { return time(); }
$dateWithFunctionCall = date('Y-m-d H:i:s', getTimestamp());
