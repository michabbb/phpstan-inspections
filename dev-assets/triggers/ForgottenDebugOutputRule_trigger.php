<?php declare(strict_types=1);

// This file demonstrates the ForgottenDebugOutputRule
// It contains various debug statements that should trigger the rule

// Positive cases - should trigger the rule
var_dump($someVariable); // Should trigger
print_r($data); // Should trigger (only 1 argument)
error_log('Debug message'); // Should trigger
dd($debugData); // Should trigger
dump($debugData); // Should trigger
phpinfo(); // Should trigger

// Negative cases - should NOT trigger the rule
print_r($data, true); // Should NOT trigger (2 arguments)
ob_start();
var_dump($bufferedData); // Should NOT trigger (buffered)

// Inside debug function - should NOT trigger
function debugFunction() {
    var_dump($localVar); // Should NOT trigger
}

// Static method call
\Some\Debug\Class::dump($data); // Should trigger

// Valid use in debug context
function someDebugFunction() {
    var_dump($debugVar); // Should NOT trigger
}