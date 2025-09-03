<?php

// Positive cases - should trigger the rule

// strpos with !== false
if (strpos('hello world', 'world') !== false) {
    echo 'Found';
}

// strpos with === false
if (strpos('hello world', 'world') === false) {
    echo 'Not found';
}

// mb_strpos with !== false
if (mb_strpos('hello world', 'world') !== false) {
    echo 'Found';
}

// mb_strpos with === false
if (mb_strpos('hello world', 'world') === false) {
    echo 'Not found';
}

// Negative cases - should NOT trigger the rule

// strpos with other comparisons
if (strpos('hello world', 'world') === 0) {
    echo 'Found at start';
}

if (strpos('hello world', 'world') > 0) {
    echo 'Found later';
}

// strpos with different operands
if (strpos('hello world', 'world') !== true) {
    echo 'Not true';
}

// strpos in other contexts
$result = strpos('hello world', 'world');
if ($result !== false) {
    echo 'Found';
}

// strpos with wrong number of arguments
if (strpos('hello world') !== false) { // This would be invalid PHP anyway
    echo 'Invalid';
}

// Different functions
if (strstr('hello world', 'world') !== false) {
    echo 'Found with strstr';
}

if (stripos('hello world', 'world') !== false) {
    echo 'Found with stripos';
}