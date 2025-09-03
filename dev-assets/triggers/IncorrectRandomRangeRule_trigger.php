<?php declare(strict_types=1);

// Positive cases - should trigger the rule
mt_rand(10, 5); // min > max
random_int(100, 50); // min > max
rand(20, 10); // min > max

// Negative cases - should not trigger the rule
mt_rand(5, 10); // min < max - correct
random_int(50, 100); // min < max - correct
rand(10, 20); // min < max - correct

// Edge cases that should not trigger (non-constant values)
$min = 5;
$max = 10;
mt_rand($min, $max); // variables - cannot determine at static analysis time

// Cases with wrong number of arguments - should not trigger
mt_rand(5); // only one argument
random_int(5, 10, 15); // three arguments