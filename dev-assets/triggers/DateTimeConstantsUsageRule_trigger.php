<?php

declare(strict_types=1);

// Test cases for DateTimeConstantsUsageRule

// These should trigger the rule (positive cases)

// 1. DateTime::ISO8601 - should suggest DateTime::ATOM
$format1 = DateTime::ISO8601;
echo date($format1);

// 2. DateTimeInterface::ISO8601 - should suggest DateTime::ATOM
$format2 = DateTimeInterface::ISO8601;
$dateTime = new DateTime();
echo $dateTime->format($format2);

// 3. DATE_ISO8601 global constant - should suggest DATE_ATOM
$format3 = DATE_ISO8601;
echo date($format3, time());

// 4. Multiple uses in same line
echo date(DateTime::ISO8601) . ' - ' . date(DATE_ISO8601);

// These should NOT trigger the rule (negative cases)

// 1. Correct ATOM constants
$correctFormat1 = DateTime::ATOM;
$correctFormat2 = DATE_ATOM;
echo date($correctFormat1);
echo date($correctFormat2);

// 2. Other DateTime constants
$format4 = DateTime::RFC3339;
$format5 = DateTime::W3C;
echo date($format4);
echo date($format5);

// 3. Different constant names
$format6 = MY_ISO8601; // Custom constant
echo date($format6);

// 4. Variables/expressions (not constants)
$myFormat = 'c';
echo date($myFormat);

// 5. Other class constants with ISO8601 name (should not trigger)
class MyClass {
    public const string ISO8601 = 'c';
}
$format7 = MyClass::ISO8601;
echo date($format7);