<?php declare(strict_types=1);

// Test cases for DateIntervalSpecificationRule

// ================= POSITIVE CASES (should trigger errors) =================

// Invalid format - missing P prefix
$invalid1 = new DateInterval('1Y2M3D');

// Invalid format - wrong character
$invalid2 = new DateInterval('PX1Y');

// Invalid format - wrong order (time part before date part)
$invalid3 = new DateInterval('T1HP1Y');

// Invalid format - invalid time part without T
$invalid4 = new DateInterval('P1H');

// Invalid format - T without time components
$invalid5 = new DateInterval('P1YT');

// Invalid format - completely wrong
$invalid6 = new DateInterval('invalid');

// Invalid format - empty string
$invalid7 = new DateInterval('');

// Invalid format - only P
$invalid8 = new DateInterval('P');

// ================= NEGATIVE CASES (should NOT trigger errors) =================

// Valid formats - basic duration patterns
$valid1 = new DateInterval('P1Y');          // 1 year
$valid2 = new DateInterval('P2M');          // 2 months  
$valid3 = new DateInterval('P3D');          // 3 days
$valid4 = new DateInterval('P4W');          // 4 weeks
$valid5 = new DateInterval('P1Y2M3D');      // 1 year, 2 months, 3 days
$valid6 = new DateInterval('P1Y2M3D4W');    // 1 year, 2 months, 3 days, 4 weeks

// Valid formats - time components
$valid7 = new DateInterval('PT1H');         // 1 hour
$valid8 = new DateInterval('PT2M');         // 2 minutes  
$valid9 = new DateInterval('PT3S');         // 3 seconds
$valid10 = new DateInterval('PT1H2M3S');    // 1 hour, 2 minutes, 3 seconds

// Valid formats - combined date and time
$valid11 = new DateInterval('P1YT1H');      // 1 year, 1 hour
$valid12 = new DateInterval('P1Y2M3DT4H5M6S'); // Complete specification

// Valid formats - datetime-like pattern (ISO 8601 alternative)
$valid13 = new DateInterval('P2023-01-15T10:30:45');

// Edge cases that should be valid
$valid14 = new DateInterval('P0Y');         // Zero years
$valid15 = new DateInterval('PT0S');        // Zero seconds

// Cases with variables (PHPStan won't analyze these)
$dynamicSpec = 'P1Y';
$dynamic1 = new DateInterval($dynamicSpec);

// Cases with method calls (PHPStan won't analyze these)
$dynamic2 = new DateInterval(getIntervalSpec());

function getIntervalSpec(): string {
    return 'P1D';
}