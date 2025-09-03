<?php

// This file demonstrates the UsingInclusionReturnValueRule
// Positive cases (should trigger the rule):
// Negative cases (should NOT trigger the rule):

// POSITIVE CASE 1: Assignment using include return value
$result = include 'config.php';

// POSITIVE CASE 2: Using include in function call
echo include 'header.php';

// POSITIVE CASE 3: Using require in arithmetic operation
$value = require 'constants.php' + 10;

// POSITIVE CASE 4: Using include_once in array
$configs = [include_once 'db_config.php'];

// POSITIVE CASE 5: Using require_once in ternary
$loaded = true ? require_once 'functions.php' : false;

// NEGATIVE CASE 1: Standalone include (correct usage)
include 'config.php';

// NEGATIVE CASE 2: Standalone require
require 'constants.php';

// NEGATIVE CASE 3: Standalone include_once
include_once 'db_config.php';

// NEGATIVE CASE 4: Standalone require_once
require_once 'functions.php';

// NEGATIVE CASE 5: Include in control structure condition (this might be edge case)
if (include 'check.php') {
    echo 'Loaded successfully';
}