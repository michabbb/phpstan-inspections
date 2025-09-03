<?php declare(strict_types=1);

// This file contains examples of deprecated random API functions
// that should trigger the RandomApiMigrationRule

// These should trigger the rule:
srand(12345);
getrandmax();
rand();
rand(1, 100);
mt_rand(1, 100);

// These should NOT trigger the rule (already modern):
mt_srand(12345);
mt_getrandmax();
random_int(1, 100);

// Edge case: rand() with 1 parameter should suggest mt_rand, not random_int
$singleParam = rand(10);

// Edge case: mt_rand() with 2 parameters should suggest random_int
$twoParams = mt_rand(1, 100);