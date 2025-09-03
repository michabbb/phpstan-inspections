<?php declare(strict_types=1);

// Simple test cases for OneTimeUseVariablesRule
// This rule detects variables that are assigned a value and then immediately
// used only once (typically in return statements or throw expressions).

function testReturnRedundantVariable(): mixed
{
    // This should trigger: variable assigned and immediately returned
    $result = someFunction();
    return $result;
}

function testThrowRedundantVariable(): void
{
    // This should trigger: exception assigned and immediately thrown
    $exception = new Exception('Test error');
    throw $exception;
}

function testSimpleValue(): mixed
{
    // This should trigger: simple value assigned and returned
    $value = 42;
    return $value;
}

function testNegativeCase(): mixed
{
    // This should NOT trigger: variable used multiple times
    $shared = getSharedValue();
    processValue($shared);
    return $shared;
}

// Helper functions
function someFunction(): string
{
    return 'test';
}

function getSharedValue(): string
{
    return 'shared';
}

function processValue(string $value): void
{
    // Process the value
}