<?php declare(strict_types=1);

// Test variables that won't be evaluated as always true/false
$foo = rand(0, 1) > 0; // Random boolean
$bar = isset($_GET['test']); // Could be true or false

// Positive cases (should trigger the rule)
$a = !!$foo; // Can be replaced with '(bool) $foo'
$b = !!!$foo; // Can be replaced with '! $foo'
$c = !!!!$foo; // Can be replaced with '(bool) $foo'
$d = !(!($foo)); // Can be replaced with '(bool) $foo'
$e = !(!(!($foo))); // Can be replaced with '! $foo'

// Test with binary expression inside
$f = !!($foo && $bar); // Can be replaced with '(bool) ($foo && $bar)'
$g = !!!($foo || $bar); // Can be replaced with '! ($foo || $bar)'

// Negative cases (should not trigger the rule)
$h = !$foo; // Single not operator
$i = ($foo || $bar); // Not a nested not operator
$j = (bool)$foo; // Explicit boolean cast