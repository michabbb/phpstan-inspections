<?php declare(strict_types=1);

// Positive test cases - should trigger the rule

class TestClassPositive {
    // Case 1: Method name matches field with callable type
    public callable $handler;

    public function handler(): void {
        // This should trigger: "There is a field with the same name, please give the method another name like is*, get*, set* and etc."
    }

    // Case 2: Method name matches field with unresolved type (mixed)
    public mixed $callback;

    public function callback(): void {
        // This should trigger: "There is a field with the same name, but its type cannot be resolved."
    }

    // Case 3: Another callable field
    public \Closure $processor;

    public function processor(): void {
        // This should trigger: "There is a field with the same name, please give the method another name like is*, get*, set* and etc."
    }
}

// Interface should not trigger (methods in interfaces are allowed)
interface TestInterface {
    public string $field;
    public function field(): string;
}