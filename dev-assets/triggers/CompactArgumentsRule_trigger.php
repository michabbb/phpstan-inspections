<?php

declare(strict_types=1);

// Positive cases - should trigger errors

/** @return array<string, mixed> */
function testUndefinedVariable(): array
{
    $name = 'John';
    $age  = 25;
    
    // This should trigger an error - $address is not defined
    return compact('name', 'age', 'address');
}

/** @return array<string, mixed> */
function testWithParameters(string $firstName, int $userAge): array
{
    $lastName = 'Doe';
    
    // This should trigger an error - $middleName is not defined
    // firstName and userAge are parameters, so they should be valid
    return compact('firstName', 'lastName', 'userAge', 'middleName');
}

/** @return array<string, mixed> */
function testMultipleUndefined(): array
{
    $validVar = 'test';
    
    // Both $nonExistent1 and $nonExistent2 should trigger errors
    return compact('validVar', 'nonExistent1', 'nonExistent2');
}

// Negative cases - should NOT trigger errors

/** @return array<string, mixed> */
function testValidCompact(): array
{
    $name = 'John';
    $age  = 25;
    $city = 'New York';
    
    // All variables are defined, should not trigger errors
    return compact('name', 'age', 'city');
}

/** @return array<string, mixed> */
function testConditionallyDefined(bool $condition): array
{
    $name = 'John';
    $age  = 25;
    
    if ($condition) {
        $city = 'New York';
    }
    
    // $city might not be defined - this should potentially trigger our custom rule
    return compact('name', 'age', 'city');
}

/** @return array<string, mixed> */
function testWithParametersValid(string $param1, int $param2): array
{
    $localVar = 'local';
    
    // All variables are defined (parameters and local variable)
    return compact('param1', 'param2', 'localVar');
}

/** @return array<string, mixed> */
function testEmptyCompact(): array
{
    // Empty compact call should not trigger errors
    return compact();
}

/** @return array<string, mixed> */
function testNonStringArguments(): array
{
    $name       = 'John';
    $dynamicKey = 'age';
    
    // Non-string arguments should be ignored by the rule
    return compact('name', $dynamicKey);
}

// Edge case - compact outside of function scope should not cause issues
$globalVar = 'test';
// This should not be analyzed since it's not in a function
/** @var array<string, mixed> $globalCompact */
$globalCompact = compact('globalVar', 'nonExistent');
