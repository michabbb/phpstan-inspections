<?php

declare(strict_types=1);

// Test cases for UnSafeIsSetOverArrayRule

function testCases(): void
{
    $array = ['key1' => 'value1', 'key2' => null];
    $variable = 'test';
    $nullVar = null;
    
    // Case 1: Simple isset on variable (should suggest null comparison)
    if (isset($variable)) {
        echo "Variable is set";
    }
    
    // Case 2: Negated isset on variable (should suggest null comparison)  
    if (!isset($variable)) {
        echo "Variable is not set";
    }
    
    // Case 3: isset on array access (should suggest array_key_exists)
    if (isset($array['key1'])) {
        echo "Array key exists";
    }
    
    // Case 4: Concatenation in array index (should report concatenation issue)
    $prefix = 'key';
    $suffix = '1';
    if (isset($array[$prefix . $suffix])) {
        echo "Concatenated key exists";
    }
    
    // Case 5: Multiple array dimensions with concatenation
    $nested = ['outer' => ['inner' => 'value']];
    if (isset($nested['out' . 'er']['in' . 'ner'])) {
        echo "Nested concatenated key exists";
    }
    
    // Valid cases that should NOT trigger warnings:
    
    // Case 6: Ternary with null fallback (should be ignored)
    $result = isset($array['key']) ? $array['key'] : null;
    
    // Case 7: Assignment (should be ignored)
    $check = isset($array['key']);
    
    // Case 8: Return statement (should be ignored)
    function returnsIsset($arr): bool
    {
        return isset($arr['key']);
    }
    
    // Case 9: Multiple arguments (should be ignored)
    if (isset($array['key1'], $array['key2'])) {
        echo "Multiple keys exist";
    }
}

// Test nested array access
function testNestedArrays(): void
{
    $data = [
        'users' => [
            'admin' => ['name' => 'Admin User']
        ]
    ];
    
    // Should suggest array_key_exists
    if (isset($data['users'])) {
        echo "Users exist";
    }
    
    // Should suggest array_key_exists  
    if (isset($data['users']['admin'])) {
        echo "Admin exists";
    }
    
    // Should report concatenation in index
    $userType = 'admin';
    if (isset($data['users'][$userType . '_backup'])) {
        echo "Backup admin exists";
    }
}

// Test with objects and properties
class TestClass
{
    public $property = 'value';
    
    public function testMethod(): void
    {
        // Should suggest null comparison for property
        if (isset($this->property)) {
            echo "Property is set";
        }
    }
}

// Global scope test (should be ignored for variables)
$globalVar = 'test';
if (isset($globalVar)) {
    echo "Global variable set";
}