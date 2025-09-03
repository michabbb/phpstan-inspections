<?php

// Positive cases - should trigger the rule

$a = 1;
$a = $a + 1; // Should suggest: $a += 1;

$b = "test";
$b = $b . "ing"; // Should suggest: $b .= "ing";

$c = 10;
$c = $c * 2; // Should suggest: $c *= 2;

$d = 100;
$d = $d / 5; // Should suggest: $d /= 5;

$e = 15;
$e = $e % 4; // Should suggest: $e %= 4;

// Bitwise operations
$f = 0b1010;
$f = $f & 0b1100; // Should suggest: $f &= 0b1100;

$g = 0b1010;
$g = $g | 0b1100; // Should suggest: $g |= 0b1100;

$h = 0b1010;
$h = $h ^ 0b1100; // Should suggest: $h ^= 0b1100;

$i = 8;
$i = $i << 2; // Should suggest: $i <<= 2;

$j = 32;
$j = $j >> 2; // Should suggest: $j >>= 2;

// Chaining cases (safe operators)
$k = 1;
$k = $k + 2 + 3; // Should suggest: $k += 2 + 3;

$l = "a";
$l = $l . "b" . "c"; // Should suggest: $l .= "b" . "c";

$m = 2;
$m = $m * 3 * 4; // Should suggest: $m *= 3 * 4;

// Negative cases - should NOT trigger the rule

$n = 1;
$o = $n + 1; // Different variable, should not trigger

$p = 1;
$p = 1 + $p; // Variable on right side, should not trigger

$q = 1;
$r = $q - 1; // Assignment to different variable, should not trigger

$s = 1;
$s = $s - 1 + 2; // Unsafe chaining for minus, should not trigger

// Array access cases
$arr = [1, 2, 3];
$arr[0] = $arr[0] + 1; // Should suggest: $arr[0] += 1;

// String manipulation (should avoid false positive)
$string = "hello";
$string[0] = $string[0] . "x"; // Should not trigger due to string manipulation