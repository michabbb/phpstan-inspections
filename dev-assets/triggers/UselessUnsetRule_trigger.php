<?php

declare(strict_types=1);

// Test cases for UselessUnsetRule

// POSITIVE CASES - should trigger the rule

function uselessUnsetExample(string $param1, int $param2): void
{
    echo "Using param1: " . $param1;
    
    // This unset is useless - only destroys local copy
    unset($param1);
    
    // Some logic that doesn't use $param1
    echo "Some other logic";
}

function multipleUselessUnset(string $name, int $age, bool $active): void
{
    if ($active) {
        echo "Name: " . $name;
    }
    
    // This unset is useless
    unset($name, $age);
    
    return;
}

class TestClass
{
    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function methodWithUselessUnset(array $data, string $key): array
    {
        $result = [];
        foreach ($data as $item) {
            $result[] = $item;
        }
        
        // This unset is useless - parameter won't be used after
        unset($data);
        unset($key);
        
        return $result;
    }
}

// NEGATIVE CASES - should NOT trigger the rule

function validUnsetOfLocalVariable(string $param): void
{
    $localVar = "some value";
    
    // This is fine - unsetting local variable, not parameter
    unset($localVar);
    
    echo "Param: " . $param;
}

function noUnsetAtAll(string $param): void
{
    echo "Just using param: " . $param;
}

function unsetAfterParameterUse(string $param): void
{
    process($param);
    
    // Still using parameter after some logic, so this might be intentional
    // This is a gray area but we'll focus on clear cases
    unset($param);
}

function process(string $input): string
{
    return strtoupper($input);
}

// Global scope unset - not in function/method
$globalVar = "test";
unset($globalVar);  // This should not trigger the rule