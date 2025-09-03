<?php

// Positive cases - should trigger errors

// Case 1: Email generation with SERVER_NAME
$email1 = "user@" . $_SERVER['SERVER_NAME'];

// Case 2: Email generation with HTTP_HOST
$email2 = "admin@" . $_SERVER['HTTP_HOST'];

// Case 3: Assignment to domain variable
$domain = $_SERVER['SERVER_NAME'];

// Case 4: Assignment to email variable
$userEmail = $_SERVER['HTTP_HOST'];

// Case 5: Assignment to host property
class TestClass {
    public $host;

    public function setHost() {
        $this->host = $_SERVER['SERVER_NAME'];
    }
}

// Case 6: Variable used later in concatenation
$serverHost = $_SERVER['HTTP_HOST'];
$fullEmail = "test@" . $serverHost;

// Negative cases - should NOT trigger errors

// Case 1: Protected by in_array check
if (in_array($_SERVER['SERVER_NAME'], ['trusted.com', 'example.org'])) {
    $safeDomain = $_SERVER['SERVER_NAME'];
}

// Case 2: Variable name doesn't match pattern
$serverName = $_SERVER['SERVER_NAME']; // This should not trigger because variable name doesn't contain domain/email/host

// Case 3: Different server attribute
$serverPort = $_SERVER['SERVER_PORT']; // Not SERVER_NAME or HTTP_HOST

// Case 4: Not in concatenation forming email
$message = "Server: " . $_SERVER['SERVER_NAME']; // No "@" at the end

// Case 5: Safe usage in logging
$logEntry = "Request from: " . $_SERVER['HTTP_HOST'];