<?php

// Negative cases - should NOT trigger CurlSslServerSpoofingRule errors

$ch = curl_init();

// Secure VERIFYHOST settings
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Should NOT trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '2'); // Should NOT trigger

// Secure VERIFYPEER settings
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1); // Should NOT trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Should NOT trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '1'); // Should NOT trigger

// Using variables that are secure
$secureHost = 2;
$securePeer = true;
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $secureHost); // Should NOT trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $securePeer); // Should NOT trigger

// Other cURL options (not SSL related)
curl_setopt($ch, CURLOPT_URL, 'https://example.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// No cURL calls at all
$someVariable = 'test';