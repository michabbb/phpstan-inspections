<?php

declare(strict_types=1);

// Test cases for PropertyInitializationFlawsRule

// Case 1: Redundant null assignment (should trigger rule)
class RedundantNullProperty
{
    private $property = null; // This should be flagged - redundant null assignment
    private string $typedProperty;
}

// Case 2: Valid nullable typed property (should NOT trigger rule)
class ValidNullableProperty
{
    private ?string $property          = null; // This is valid - nullable typed property
    private string|null $unionProperty = null; // This is also valid
}

// Case 3: Constructor overriding default (should trigger rule)
class ConstructorOverride
{
    private string $property = 'default'; // Should be flagged as constructor overrides it
    
    public function __construct()
    {
        $this->property = 'new value'; // This assignment makes the default unnecessary
    }
}

// Case 4: Redundant assignment in constructor (should trigger rule)
class RedundantConstructorAssignment
{
    private ?string $property = null; // Default null
    
    public function __construct()
    {
        $this->property = null; // This should be flagged as redundant
    }
}

// Case 5: Valid constructor initialization (should NOT trigger rule)
class ValidConstructorInitialization
{
    private string $property;
    
    public function __construct()
    {
        $this->property = 'initialized'; // This is necessary initialization
    }
}

// Case 6: Property reused in assignment (should NOT trigger rule)
class PropertyReusedInAssignment
{
    private int $counter = 0;
    
    public function __construct()
    {
        $this->counter = $this->counter + 1; // Property is reused, so default makes sense
    }
}

// Case 7: Mixed valid and invalid cases
class MixedCases
{
    private $redundantNull        = null; // Should be flagged
    private string $validProperty = 'default'; // Should be flagged if constructor overrides
    private ?int $nullableInt     = null; // Should NOT be flagged - nullable typed
    
    public function __construct()
    {
        $this->validProperty = 'overridden'; // This makes the default unnecessary
        // $this->nullableInt remains null, which is valid
    }
}

// Case 8: No properties (should not trigger anything)
class EmptyClass
{
}

// Case 9: Only constants (should not trigger anything)
class OnlyConstants
{
    public const string STATUS_ACTIVE = 'active';
    private const int MAX_ITEMS       = 100;
}

// Case 10: Static properties (behavior may vary based on implementation)
class StaticProperties
{
    private static $staticNull          = null; // Depends on implementation if this should be flagged
    private static string $staticString = 'default';
}
