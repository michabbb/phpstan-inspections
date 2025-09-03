<?php declare(strict_types=1);

// Negative test cases - should NOT trigger the rule

class TestClassNegative {
    // Case 1: Method name matches field but field is not callable and type is resolved
    public string $name;

    public function name(): string {
        // This should NOT trigger - field type is resolved and not callable
        return $this->name;
    }

    // Case 2: Method name matches field but field is static
    public static string $staticField;

    public function staticField(): string {
        // This should NOT trigger - static fields are ignored
        return self::$staticField;
    }

    // Case 3: Method name matches field but field has non-callable resolved type
    public int $count;

    public function count(): int {
        // This should NOT trigger - field type is resolved and not callable
        return $this->count;
    }

    // Case 4: Different method and field names
    public callable $handler;

    public function process(): void {
        // This should NOT trigger - method name doesn't match field name
        ($this->handler)();
    }

    // Case 5: No matching field
    public function standalone(): void {
        // This should NOT trigger - no field with this name exists
    }
}

// Interface methods are allowed to match field names
interface TestInterfaceNegative {
    public callable $handler;
    public function handler(): void; // This should NOT trigger
}