<?php

declare(strict_types=1);

// Test cases for UnusedFunctionParameterRule
// This file should trigger errors for unused function/method parameters

// POSITIVE CASES - should trigger errors

class TestUnusedParameters
{
    // Method with unused parameter - should trigger
    public function methodWithUnusedParam(string $used, string $unused): string
    {
        return "Result: " . $used;
        // $unused parameter should trigger: "Parameter $unused is unused in TestUnusedParameters::methodWithUnusedParam()."
    }

    // Method with multiple unused parameters - should trigger multiple errors
    public function methodWithMultipleUnused(string $used, string $unused1, string $unused2, string $unused3): string
    {
        echo $used;
        return "done";
        // $unused1, $unused2, $unused3 should all trigger errors
    }

    // Method with typed unused parameter - should trigger
    public function methodWithTypedUnused(int $count, array $data): int
    {
        return $count * 2;
        // $data should trigger error
    }

    // Static method with unused parameter - should trigger
    public static function staticMethodUnused(string $param1, string $param2): string
    {
        return $param1;
        // $param2 should trigger error
    }

    // Private method with unused parameter - should trigger
    private function privateMethodUnused(bool $flag, string $message): bool
    {
        return $flag;
        // $message should trigger error
    }

    // Protected method with unused parameter - should trigger
    protected function protectedMethodUnused(float $value1, float $value2): float
    {
        return $value1 + 10.0;
        // $value2 should trigger error
    }
}

// Regular function with unused parameter - should trigger
function testFunctionUnused(string $param1, string $param2): string
{
    return "Hello " . $param1;
    // $param2 should trigger error
}

// Function with multiple unused parameters - should trigger
function testMultipleUnused(int $a, int $b, int $c, int $d): int
{
    return $a + $c;
    // $b and $d should trigger errors
}

// Function with nullable unused parameter - should trigger
function testNullableUnused(?string $used, ?string $unused): ?string
{
    return $used;
    // $unused should trigger error
}

// NEGATIVE CASES - should NOT trigger errors

class TestValidParameters
{
    // Constructor - should be ignored (no errors expected)
    public function __construct(string $unusedInConstructor)
    {
        // Constructor parameters are ignored even if unused
    }

    // Magic method - should be ignored (no errors expected)
    public function __call(string $name, array $arguments)
    {
        // Magic method parameters are ignored even if unused
        return "magic";
    }

    // Magic method __invoke - should be ignored
    public function __invoke(string $unusedParam)
    {
        // Magic method parameters are ignored
        return "invoked";
    }

    // Method with underscore parameter - should be ignored
    public function methodWithUnderscoreParam(string $used, string $_intentionallyUnused): string
    {
        return $used;
        // $_intentionallyUnused should be ignored due to underscore prefix
    }

    // Method with reference parameter - should be ignored
    public function methodWithReferenceParam(string $used, string &$byReference): string
    {
        // By-reference parameters are ignored even if not used
        return $used;
    }

    // Method with all parameters used - should not trigger
    public function methodAllUsed(string $param1, string $param2, string $param3): string
    {
        return $param1 . $param2 . $param3;
    }

    // Method with parameter used in assignment - should not trigger
    public function methodUsedInAssignment(string $param): string
    {
        $result = $param . " modified";
        return $result;
    }

    // Abstract method - should be ignored (no implementation to check)
    abstract public function abstractMethod(string $param);
}

// Interface method - should be ignored (no implementation)
interface TestInterface
{
    public function interfaceMethod(string $param);
}

// Function with all parameters used - should not trigger
function testAllUsed(string $a, string $b): string
{
    return $a . $b;
}

// Function with parameter used in condition - should not trigger
function testUsedInCondition(bool $flag, string $message): string
{
    if ($flag) {
        return $message;
    }
    return "default";
}

// Function with parameter used in compound assignment - should not trigger
function testUsedInCompoundAssignment(int $base, int $increment): int
{
    $base += $increment;
    return $base;
}

// Function with parameter used in compact() - should not trigger
function testUsedInCompact(string $customer, string $sku, bool $debug): array
{
    return compact('customer', 'sku');
    // $debug is unused and should still trigger error
}

// Method with parameters used in compact() with array - should not trigger for customer/sku
class TestCompactUsage
{
    public function methodWithCompactArray(string $customer, string $sku, string $unused): array
    {
        return compact(['customer', 'sku']);
        // $unused should trigger error
    }

    // Method with mixed compact() usage - should not trigger for used parameters
    public function methodWithMixedCompact(string $var1, string $var2, string $var3, string $unused): array
    {
        return compact('var1', ['var2', 'var3']);
        // $unused should trigger error
    }
}