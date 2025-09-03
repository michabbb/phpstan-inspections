<?php

// Positive cases - should trigger the rule

// Regular aliases
$dir = closedir($handle); // This should NOT trigger (original function)
$dir = close($handle); // This SHOULD trigger - alias of closedir

$isFloat = is_float(1.5); // This should NOT trigger (original function)
$isFloat = is_double(1.5); // This SHOULD trigger - alias of is_float

$isInt = is_int(42); // This should NOT trigger (original function)
$isInt = is_integer(42); // This SHOULD trigger - alias of is_int
$isInt = is_long(42); // This SHOULD trigger - alias of is_int

$count = count([1, 2, 3]); // This should NOT trigger (original function)
$count = sizeof([1, 2, 3]); // This SHOULD trigger - alias of count

$float = floatval('1.5'); // This should NOT trigger (original function)
$float = doubleval('1.5'); // This SHOULD trigger - alias of floatval

$wrote = fwrite($handle, 'data'); // This should NOT trigger (original function)
$wrote = fputs($handle, 'data'); // This SHOULD trigger - alias of fwrite

$imploded = implode(',', [1, 2, 3]); // This should NOT trigger (original function)
$imploded = join(',', [1, 2, 3]); // This SHOULD trigger - alias of implode

$exists = array_key_exists('key', $array); // This should NOT trigger (original function)
$exists = key_exists('key', $array); // This SHOULD trigger - alias of array_key_exists

$trimmed = rtrim('text '); // This should NOT trigger (original function)
$trimmed = chop('text '); // This SHOULD trigger - alias of rtrim

// Deprecated aliases
$result = mysqli_real_escape_string($link, $string); // This should NOT trigger (original function)
$result = mysqli_escape_string($link, $string); // This SHOULD trigger - deprecated alias

$stmt = mysqli_stmt_execute($stmt); // This should NOT trigger (original function)
$stmt = mysqli_execute($stmt); // This SHOULD trigger - deprecated alias

// Negative cases - should NOT trigger the rule

// Functions with namespace prefix (should not trigger)
$result = \MyNamespace\close($handle);
$result = MyNamespace\is_double(1.5);

// Non-alias functions (should not trigger)
$result = custom_function();
$result = another_function();

// Variable function calls (should not trigger)
$func = 'close';
$result = $func($handle);

// Object method calls (should not trigger)
$obj = new MyClass();
$result = $obj->close();

// Static method calls (should not trigger)
$result = MyClass::close();