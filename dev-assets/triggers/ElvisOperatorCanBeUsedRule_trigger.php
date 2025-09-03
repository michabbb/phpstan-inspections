<?php declare(strict_types=1);

// Test variables
$a = null;
$b = 'default';
$c = 'other';

// Positive cases - should trigger the rule
$result1 = $a ? $a : $b; // Should trigger Elvis operator
$result2 = isset($a) ? $a : $b; // Should trigger Null coalescing operator
$result3 = $a !== null ? $a : $b; // Should trigger Null coalescing operator

// More realistic positive cases
$userInput = null;
$fallback = 'guest';
$result4 = $userInput ? $userInput : $fallback; // Should trigger Elvis operator

$configValue = null;
$defaultConfig = ['setting' => 'value'];
$result5 = isset($configValue) ? $configValue : $defaultConfig; // Should trigger Null coalescing operator

$name = null;
$defaultName = 'Anonymous';
$result6 = $name !== null ? $name : $defaultName; // Should trigger Null coalescing operator

// Negative cases - should NOT trigger the rule
$result7 = $a ? $b : $c; // Different values, should not trigger
$result8 = $a === null ? $b : $a; // Different condition, should not trigger
$result9 = $a ? $a : null; // Null as else, should not trigger
$result10 = $a ?: $b; // Already uses Elvis operator
$result11 = $a ?? $b; // Already uses Null coalescing operator
