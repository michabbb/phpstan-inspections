<?php

// This file demonstrates violations of the CryptographicallySecureAlgorithmsRule
// It should trigger PHPStan errors for insecure cryptographic constants

// Insecure mcrypt constants
$algorithm1 = MCRYPT_3DES; // Should trigger: 3DES has known vulnerabilities
$algorithm2 = MCRYPT_DES;  // Should trigger: DES has known vulnerabilities
$algorithm3 = MCRYPT_RC4;  // Should trigger: RC4 has known vulnerabilities

// Insecure openssl constants
$cipher1 = OPENSSL_CIPHER_3DES; // Should trigger: 3DES has known vulnerabilities
$cipher2 = OPENSSL_CIPHER_DES;  // Should trigger: DES has known vulnerabilities

// Insecure crypt constants
$hash1 = CRYPT_MD5;      // Should trigger: MD5 has known vulnerabilities
$hash2 = CRYPT_STD_DES;  // Should trigger: DES has known vulnerabilities

// These should NOT trigger (secure alternatives)
$secureAlgorithm = MCRYPT_RIJNDAEL_128; // AES-128, should not trigger
$secureCipher = 'aes-128-cbc'; // String literal, should not trigger
$secureHash = CRYPT_BLOWFISH; // Should not trigger