<?php declare(strict_types=1);

/**
 * Trigger script for DebugRule
 * This rule should trigger on any expression statement
 */

// Simple expressions that should trigger the debug rule
$variable = 'test';
echo $variable;

function testFunction(): void {
    $localVar = 42;
    echo $localVar;
}

testFunction();

// More expressions
$result = 1 + 2;
$array = ['key' => 'value'];
$array['new_key'] = 'new_value';