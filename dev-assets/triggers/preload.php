<?php
declare(strict_types=1);

// Test cases for PreloadingUsageCorrectnessRule
// This file is named preload.php to trigger the rule

// Case 1: include statement (should trigger error)
include __DIR__ . '/vendor/autoload.php';

// Case 2: include_once statement (should trigger error)
include_once __DIR__ . '/src/SomeClass.php';

// Case 3: require statement (should trigger error)
require __DIR__ . '/config/app.php';

// Case 4: require_once statement (should trigger error)
require_once __DIR__ . '/bootstrap/bootstrap.php';

// Case 5: include with variable (should trigger error)
$configPath = __DIR__ . '/config/database.php';
include $configPath;

// Case 6: Multiple includes (should trigger multiple errors)
include __DIR__ . '/src/Model.php';
include __DIR__ . '/src/Controller.php';
include __DIR__ . '/src/View.php';

// Case 7: include in conditional (should trigger error)
if (file_exists(__DIR__ . '/optional.php')) {
    include __DIR__ . '/optional.php';
}

// Case 8: include with complex expression (should trigger error)
include dirname(__FILE__) . '/helpers/functions.php';

// Case 9: require_once in try-catch (should trigger error)
try {
    require_once __DIR__ . '/vendor/risky-package/autoload.php';
} catch (Exception $e) {
    // Handle error
}

// Case 10: Proper usage - should NOT trigger errors
opcache_compile_file(__DIR__ . '/src/FastClass.php');
opcache_compile_file(__DIR__ . '/src/CoreClass.php');
opcache_compile_file(__DIR__ . '/vendor/autoload.php');

// Case 11: Mixed usage (should trigger errors for includes only)
opcache_compile_file(__DIR__ . '/src/ProperClass.php'); // OK
include __DIR__ . '/src/BadClass.php'; // Should trigger
opcache_compile_file(__DIR__ . '/src/AnotherProperClass.php'); // OK

// Case 12: Function calls - should NOT trigger
function loadOptionalConfig() {
    if (file_exists(__DIR__ . '/config/optional.php')) {
        include __DIR__ . '/config/optional.php';
    }
}

// Case 13: Array of files to include (should trigger for each include)
$files = [
    __DIR__ . '/src/ClassA.php',
    __DIR__ . '/src/ClassB.php',
    __DIR__ . '/src/ClassC.php'
];

foreach ($files as $file) {
    include $file; // Should trigger error
}

// Case 14: Proper preloading pattern
$preloadFiles = [
    __DIR__ . '/src/CriticalClass.php',
    __DIR__ . '/src/PerformanceClass.php',
    __DIR__ . '/vendor/fast-package/FastLibrary.php'
];

foreach ($preloadFiles as $file) {
    opcache_compile_file($file); // Proper usage - no error
}

// Case 15: Complex include expression (should trigger error)
require_once realpath(__DIR__ . '/../config') . '/settings.php';

// Case 16: Nested includes in blocks
{
    include __DIR__ . '/block-scoped.php'; // Should trigger
}

// Case 17: Include with error suppression (should still trigger)
@include __DIR__ . '/potentially-missing.php';