<?php

// Positive cases - should trigger the rule

// Using insecure IV for openssl_encrypt
$data = 'sensitive data';
$key = 'encryption key';
$iv = '1234567890123456'; // insecure - hardcoded string
$result = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

// Using insecure IV for mcrypt_encrypt
$iv2 = 123456789; // insecure - hardcoded number
$result2 = mcrypt_encrypt('rijndael-128', $key, $data, 'cbc', $iv2);

// Using variable that might be insecure
$someVar = 'insecure';
$result3 = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $someVar);

// Using array as IV (insecure)
$arrayIv = ['not', 'secure'];
$result4 = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $arrayIv);

// Negative cases - should NOT trigger the rule

// Using secure random functions
$secureIv1 = random_bytes(16);
$result5 = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $secureIv1);

$secureIv2 = openssl_random_pseudo_bytes(16);
$result6 = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $secureIv2);

$secureIv3 = mcrypt_create_iv(16, MCRYPT_RAND);
$result7 = mcrypt_encrypt('rijndael-128', $key, $data, 'cbc', $secureIv3);

// Function with less than 5 parameters (should not trigger)
$result8 = openssl_encrypt($data, 'AES-256-CBC', $key, 0); // missing IV parameter

// Different function (not openssl_encrypt or mcrypt_encrypt)
$result9 = hash('sha256', $data); // should not trigger