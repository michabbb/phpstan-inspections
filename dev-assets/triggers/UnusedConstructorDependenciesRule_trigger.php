<?php declare(strict_types=1);

/**
 * Trigger script for UnusedConstructorDependenciesRule
 *
 * This script contains test cases to verify the rule detects:
 * - Private properties assigned in constructor but never used elsewhere
 */

// Case 1: Property assigned in constructor but never used (should trigger error)
class UnusedDependencyExample
{
    private string $unusedProperty;

    public function __construct(string $value)
    {
        $this->unusedProperty = $value; // This should trigger the rule
    }

    public function doSomething(): void
    {
        // $this->unusedProperty is never used here
        echo "Doing something";
    }
}

// Case 2: Property assigned in constructor and used elsewhere (should NOT trigger error)
class UsedDependencyExample
{
    private string $usedProperty;

    public function __construct(string $value)
    {
        $this->usedProperty = $value; // This should NOT trigger the rule
    }

    public function doSomething(): void
    {
        echo $this->usedProperty; // Property is used here
    }
}

// Case 3: Multiple unused properties (should trigger multiple errors)
class MultipleUnusedDependenciesExample
{
    private string $unused1;
    private string $unused2;
    private string $used;

    public function __construct(string $val1, string $val2, string $val3)
    {
        $this->unused1 = $val1; // Should trigger
        $this->unused2 = $val2; // Should trigger
        $this->used = $val3;    // Should NOT trigger
    }

    public function doSomething(): void
    {
        echo $this->used; // Only this one is used
    }
}

// Case 4: Property with PHPDoc annotation (should NOT trigger error)
class AnnotatedPropertyExample
{
    /** @var string @SomeAnnotation */
    private string $annotatedProperty;

    public function __construct(string $value)
    {
        $this->annotatedProperty = $value; // Should NOT trigger due to annotation
    }

    public function doSomething(): void
    {
        // Property not used, but annotation should exclude it
    }
}

// Case 5: Static property (should NOT trigger error - rule only checks instance properties)
class StaticPropertyExample
{
    private static string $staticProperty;

    public function __construct(string $value)
    {
        self::$staticProperty = $value; // Should NOT trigger - static property
    }
}

// Case 6: Public property (should NOT trigger error - rule only checks private properties)
class PublicPropertyExample
{
    public string $publicProperty;

    public function __construct(string $value)
    {
        $this->publicProperty = $value; // Should NOT trigger - public property
    }

    public function doSomething(): void
    {
        // Not using the property
    }
}