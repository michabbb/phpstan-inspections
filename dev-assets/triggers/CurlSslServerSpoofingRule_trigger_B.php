<?php

// Positive cases - should trigger CurlSslServerSpoofingRule errors

$ch = curl_init();

// Insecure VERIFYHOST settings
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '0'); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '1'); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Should trigger

// Insecure VERIFYPEER settings
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0'); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 'false'); // Should trigger

// Mixed insecure settings
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Should trigger both

// Using variables that might be insecure
$insecureHost = 0;
$insecurePeer = false;
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $insecureHost); // Should trigger
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $insecurePeer); // Should trigger