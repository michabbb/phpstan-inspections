<?php

declare(strict_types=1);

// Test cases for SimpleXmlLoadFileUsageRule

// These calls should trigger the rule and report the PHP bug warning
$xml1 = simplexml_load_file('file.xml'); // Should trigger

$xml2 = simplexml_load_file('data.xml', 'SimpleXMLElement', LIBXML_NOCDATA); // Should trigger

$xml3 = simplexml_load_file('/path/to/file.xml', null, LIBXML_NOCDATA, 'http://example.com'); // Should trigger

// Dynamic filename - should still trigger
$filename = 'dynamic.xml';
$xml4     = simplexml_load_file($filename); // Should trigger

// These calls should NOT trigger the rule (alternative functions)
$xml_string = simplexml_load_string('<xml>data</xml>'); // Should NOT trigger

$file_contents = file_get_contents('file.xml');
$xml5          = simplexml_load_string($file_contents); // Should NOT trigger - this is the recommended approach

// Edge case: function call in variable or method name (should NOT trigger)
$functionName = 'simplexml_load_file';
// $xml6 = $functionName('file.xml'); // Dynamic call - not detectable at static analysis time

// Edge case: Namespaced function (should NOT trigger since it's a different function)
// namespace MyNamespace;
// function simplexml_load_file($file) { return null; }
// $xml7 = simplexml_load_file('file.xml'); // Different function, not PHP's built-in
