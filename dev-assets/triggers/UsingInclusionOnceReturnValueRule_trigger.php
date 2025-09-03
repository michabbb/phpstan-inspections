<?php declare(strict_types=1);

// Positive cases (should trigger the rule)

$value1 = include_once 'some_file.php';
$value2 = require_once 'another_file.php';

if (include_once 'conditional_file.php') {
    echo 'File included conditionally.';
}

function getValueFromIncludeOnce(): bool
{
    return include_once 'function_file.php';
}

// Negative cases (should NOT trigger the rule)

include_once 'just_include.php'; // Return value not used
require_once 'just_require.php'; // Return value not used

include 'normal_include.php'; // Not _once
require 'normal_require.php'; // Not _once

$value3 = include 'normal_include_with_value.php'; // Not _once
