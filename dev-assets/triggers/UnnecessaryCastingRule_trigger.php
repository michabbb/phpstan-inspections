<?php declare(strict_types=1);

namespace App\Test;

class UnnecessaryCastingTrigger
{
    public function testUnnecessaryCasting(): void
    {
        // Unnecessary casting - already int
        $intValue = 42;
        $result1 = (int) $intValue; // Should trigger: unnecessary cast

        // Unnecessary casting - already string
        $stringValue = 'hello';
        $result2 = (string) $stringValue; // Should trigger: unnecessary cast

        // Unnecessary casting - already bool
        $boolValue = true;
        $result3 = (bool) $boolValue; // Should trigger: unnecessary cast

        // Unnecessary casting - already float
        $floatValue = 3.14;
        $result4 = (float) $floatValue; // Should trigger: unnecessary cast

        // Unnecessary casting - already array
        $arrayValue = [1, 2, 3];
        $result5 = (array) $arrayValue; // Should trigger: unnecessary cast

        // String casting in concatenation context - should trigger
        $num = 123;
        $result6 = 'Value: ' . (string) $num; // Should trigger: unnecessary in concatenation

        // String casting in self-assignment concatenation - should trigger
        $str = 'initial';
        $str .= (string) $num; // Should trigger: unnecessary in .=

        // Valid casting - different types (should NOT trigger)
        $stringToInt = (int) '123'; // Valid: string to int
        $intToString = (string) 456; // Valid: int to string
        $boolToInt = (int) true; // Valid: bool to int

        // Casting in non-concatenation context (should NOT trigger)
        $standaloneCast = (string) $num; // Valid: not in concatenation
    }

    public function testWeakTypedParameter(): void
    {
        // This should NOT trigger because it's a weakly typed parameter
        $this->processWeakParam('test');
    }

    private function processWeakParam($param): void // No type hint = weak typing
    {
        $result = (string) $param; // Should NOT trigger: weak typed parameter
    }

    public function testNullCoalescing(): void
    {
        $data = ['key' => null];
        $value = $data['key'] ?? (string) 'default'; // Should NOT trigger: null coalescing
    }
}