<?php

declare(strict_types=1);

// Test cases for PrintfScanfArgumentsRule

// === POSITIVE CASES (should trigger errors) ===

// Invalid pattern format
printf("Invalid %q format", 42); // Should trigger: invalid pattern (q is not a valid format specifier)

// Argument count mismatch - too few arguments
printf("Hello %s, you are %d years old", "John"); // Should trigger: expects 2 parameters
sprintf("Value: %s %d %f", "test", 42); // Should trigger: expects 3 parameters

// Argument count mismatch - too many arguments  
printf("Simple: %s", "hello", "extra"); // Should trigger: expects 1 parameter
fprintf(STDERR, "Message: %s", "test", "extra", "more"); // Should trigger: expects 2 parameters

// fscanf/sscanf with wrong argument count
$data = "test 123";
sscanf($data, "%s %d", $str, $num, $extra); // Should trigger: expects 3 parameters

// === NEGATIVE CASES (should NOT trigger errors) ===

// Correct usage
printf("Hello %s, you are %d years old", "John", 25);
sprintf("Value: %s", "test");
fprintf(STDERR, "Error: %s in line %d", "syntax error", 42);

// Correct fscanf/sscanf usage
$data = "test 123";
sscanf($data, "%s %d", $str, $num);

// fscanf/sscanf returning array (valid usage)
$data = "test 123 456";
$result = sscanf($data, "%s %d %d"); // Returns array, valid

// Escaped %% (should be ignored)
printf("Progress: 100%% complete");

// Position specifiers
printf("Second: %2\$s, First: %1\$s", "first", "second");

// Variadic arguments (should be ignored)
$args = ["test1", "test2", "test3"];
printf("Values: %s %s %s", ...$args);

// Complex valid patterns
printf("Float: %.2f, Hex: %X, Padded: %08d", 3.14159, 255, 42);
sprintf("%-10s | %+d | %x", "left", -123, 255);

// Variables in pattern (should be skipped)
$pattern = "Dynamic %s pattern";
printf($pattern, "test");