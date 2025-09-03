<?php

declare(strict_types=1);

// Positive cases - should trigger the rule

// Function to constant replacements
$phpVersion = phpversion(); // Should suggest PHP_VERSION
$sapi       = php_sapi_name(); // Should suggest PHP_SAPI
$className  = get_class(); // Should suggest __CLASS__
$pi         = pi(); // Should suggest M_PI

// Version comparison improvements
if (version_compare(PHP_VERSION, '7.4', '>=')) { // Should suggest PHP_VERSION_ID >= 70400
    echo 'PHP 7.4+';
}

if (version_compare(PHP_VERSION, '8.0', '<')) { // Should suggest PHP_VERSION_ID < 80000
    echo 'Before PHP 8.0';
}

if (version_compare(PHP_VERSION, '8.1.5', '!=')) { // Should suggest PHP_VERSION_ID !== 80105
    echo 'Not PHP 8.1.5';
}

// Negative cases - should NOT trigger the rule

// Functions with arguments - should not trigger
$phpVersionExtension = phpversion('json'); // Has argument, should not trigger
$class               = get_class(); // No object, should not trigger (invalid call but not our rule's concern)

// Different function name
$time = time(); // Not in our mapping, should not trigger

// Version compare with different format
version_compare('1.0', '2.0', '>'); // Not using PHP_VERSION, should not trigger
version_compare(PHP_VERSION, 'invalid-version', '>'); // Invalid version format, should not trigger
version_compare(PHP_VERSION, '7.4'); // Missing operator, should not trigger

// Valid usage - constants already used
echo PHP_VERSION; // Already using constant, should not trigger
echo PHP_SAPI; // Already using constant, should not trigger
echo __CLASS__; // Already using constant, should not trigger
echo M_PI; // Already using constant, should not trigger

if (PHP_VERSION_ID >= 70400) { // Already using PHP_VERSION_ID, should not trigger
    echo 'PHP 7.4+';
}
