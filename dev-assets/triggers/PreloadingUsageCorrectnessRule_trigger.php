<?php
declare(strict_types=1);

// Test cases for PreloadingUsageCorrectnessRule
// This is a regular PHP file - should NOT trigger any errors from this rule

// Case 1: include statement in non-preload file (should NOT trigger)
include __DIR__ . '/vendor/autoload.php';

// Case 2: include_once statement in non-preload file (should NOT trigger)
include_once __DIR__ . '/src/SomeClass.php';

// Case 3: require statement in non-preload file (should NOT trigger)
require __DIR__ . '/config/app.php';

// Case 4: require_once statement in non-preload file (should NOT trigger)
require_once __DIR__ . '/bootstrap/bootstrap.php';

// Case 5: opcache_compile_file in non-preload file (should NOT trigger)
opcache_compile_file(__DIR__ . '/src/SomeClass.php');

// Note: The PreloadingUsageCorrectnessRule only applies to files named 'preload.php'
// For actual testing of the rule, use the preload.php file in the same directory
// This file demonstrates that the rule should NOT trigger for regular PHP files

class TestClass {
    public function loadDependencies() {
        // These includes in a regular file should not trigger the preloading rule
        require_once __DIR__ . '/dependencies/ClassA.php';
        require_once __DIR__ . '/dependencies/ClassB.php';
        include __DIR__ . '/optional/helpers.php';
    }
    
    public function preloadFiles() {
        // Using opcache_compile_file in non-preload context
        opcache_compile_file(__DIR__ . '/src/CriticalClass.php');
    }
}

// Various include patterns that are fine in regular PHP files
$configFiles = ['config.php', 'database.php', 'cache.php'];
foreach ($configFiles as $file) {
    include __DIR__ . '/config/' . $file;
}

// Conditional includes
if (extension_loaded('redis')) {
    include __DIR__ . '/cache/redis.php';
}

// Dynamic includes
$moduleName = 'user';
require_once __DIR__ . '/modules/' . $moduleName . '.php';

// All of the above should be perfectly fine in a regular PHP file
// and should NOT trigger the PreloadingUsageCorrectnessRule