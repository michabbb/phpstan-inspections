<?php

// Positive cases - should trigger the rule

// Case 1: realpath in include context
include realpath(__DIR__ . '/../vendor/autoload.php');
require realpath(__DIR__ . '/../../config.php');

// Case 2: realpath with paths containing '..'
$path1 = realpath(__DIR__ . '/../src/File.php');
$path2 = realpath('/some/path/../../../file.php');
$path3 = realpath('./../parent/file.txt');

// Case 3: realpath in require_once context
require_once realpath(__DIR__ . '/../lib/functions.php');

// Negative cases - should NOT trigger the rule

// Normal realpath usage without issues
$absolutePath = realpath('/tmp/somefile.txt');
$currentDir = realpath('.');
$parentDir = realpath('..'); // This should NOT trigger because it's just '..' not in a path with other components

// Other function calls (should not be affected)
$basename = basename('/path/to/file.txt');
$dirname = dirname('/path/to/file.txt');

// Complex expressions that don't contain '..'
$complexPath = realpath(__DIR__ . '/subdir/file.php');
$anotherPath = realpath('/absolute/path/without/dots/file.txt');

// Edge cases
$emptyRealpath = realpath(''); // Empty string
$nullRealpath = realpath(null); // This would be a different error, not our rule

// Mixed usage - only the problematic ones should trigger
$good = realpath('/good/path.txt');
$bad = realpath(__DIR__ . '/../bad/path.txt'); // Should trigger
$alsoGood = realpath('./current.txt'); // Should not trigger - no '..'

// Include without realpath (should not trigger)
include __DIR__ . '/file.php';
require_once './another.php';