<?php

// This file should trigger the BacktickOperatorUsageRule

$result = `ls -la`;
echo $result;

$output = `echo "Hello World"`;
var_dump($output);