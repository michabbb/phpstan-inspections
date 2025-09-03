<?php

// This file should NOT trigger the BacktickOperatorUsageRule

$result = shell_exec('ls -la');
echo $result;

$output = shell_exec('echo "Hello World"');
var_dump($output);

// Regular string literals should not trigger
$command = 'ls -la';
$backtickString = "`not a command`"; // This is just a string, not an execution