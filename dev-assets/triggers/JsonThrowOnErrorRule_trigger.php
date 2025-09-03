<?php declare(strict_types=1);

namespace Test\JsonThrowOnError;

// Test cases for JsonThrowOnErrorRule
// This rule detects json_encode/json_decode calls without JSON_THROW_ON_ERROR flag

$data = ['key' => 'value'];
$jsonString = '{"key": "value"}';

// Positive cases - should trigger errors (missing JSON_THROW_ON_ERROR)

// json_encode without flags
$result1 = json_encode($data);

// json_decode without flags
$result2 = json_decode($jsonString);

// json_encode with other flags but missing JSON_THROW_ON_ERROR
$result3 = json_encode($data, JSON_PRETTY_PRINT);

// json_decode with other flags but missing JSON_THROW_ON_ERROR
$result4 = json_decode($jsonString, true, 512, JSON_BIGINT_AS_STRING);

// json_encode with flags=0 (explicitly no flags)
$result5 = json_encode($data, 0);

// json_decode with flags=0 (explicitly no flags)
$result6 = json_decode($jsonString, true, 512, 0);

// Negative cases - should NOT trigger errors (has JSON_THROW_ON_ERROR)

// json_encode with JSON_THROW_ON_ERROR
$result7 = json_encode($data, JSON_THROW_ON_ERROR);

// json_decode with JSON_THROW_ON_ERROR
$result8 = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);

// json_encode with JSON_THROW_ON_ERROR and other flags (bitwise OR)
$result9 = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

// json_decode with JSON_THROW_ON_ERROR and other flags (bitwise OR)
$result10 = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);

// Named arguments (PHP 8.0+)

// json_encode with named flags parameter
$result11 = json_encode(value: $data, flags: JSON_THROW_ON_ERROR);

// json_decode with named flags parameter
$result12 = json_decode(json: $jsonString, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);

// json_encode with named flags but missing JSON_THROW_ON_ERROR
$result13 = json_encode(value: $data, flags: JSON_PRETTY_PRINT);

// json_decode with named flags but missing JSON_THROW_ON_ERROR
$result14 = json_decode(json: $jsonString, associative: true, depth: 512, flags: JSON_BIGINT_AS_STRING);