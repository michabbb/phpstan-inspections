<?php declare(strict_types=1);

// Positive cases

// Case 1: openssl_random_pseudo_bytes without second argument
$bytes1 = openssl_random_pseudo_bytes(16);

// Case 2: mcrypt_create_iv without second argument
$iv1 = mcrypt_create_iv(16);

// Case 3: openssl_random_pseudo_bytes with second argument not checked
$cryptoStrong1;
$bytes2 = openssl_random_pseudo_bytes(16, $cryptoStrong1);

// Case 4: mcrypt_create_iv with insecure second argument
$iv2 = mcrypt_create_iv(16, MCRYPT_RAND);

// Case 5: openssl_random_pseudo_bytes return value not checked
openssl_random_pseudo_bytes(16);

// Case 6: mcrypt_create_iv return value not checked
mcrypt_create_iv(16, MCRYPT_DEV_RANDOM);


// Negative cases

// Case 7: openssl_random_pseudo_bytes with second argument checked
$cryptoStrong2;
$bytes3 = openssl_random_pseudo_bytes(16, $cryptoStrong2);
if ($bytes3 === false || $cryptoStrong2 === false) {
    // Handle error
}

// Case 8: mcrypt_create_iv with MCRYPT_DEV_RANDOM
$iv3 = mcrypt_create_iv(16, MCRYPT_DEV_RANDOM);

// Case 9: openssl_random_pseudo_bytes return value checked
$bytes4 = openssl_random_pseudo_bytes(16);
if ($bytes4 === false) {
    // Handle error
}

// Case 10: openssl_random_pseudo_bytes return value checked with negation
$bytes5 = openssl_random_pseudo_bytes(16);
if (!$bytes5) {
    // Handle error
}

// Case 11: openssl_random_pseudo_bytes with second argument checked with negation
$cryptoStrong3;
$bytes6 = openssl_random_pseudo_bytes(16, $cryptoStrong3);
if (!$bytes6 || !$cryptoStrong3) {
    // Handle error
}

// Case 12: openssl_random_pseudo_bytes with second argument checked directly in if
$cryptoStrong4;
if (openssl_random_pseudo_bytes(16, $cryptoStrong4) === false || $cryptoStrong4 === false) {
    // Handle error
}

// Case 13: openssl_random_pseudo_bytes with second argument checked directly in if with negation
$cryptoStrong5;
if (!openssl_random_pseudo_bytes(16, $cryptoStrong5) || !$cryptoStrong5) {
    // Handle error
}
