<?php declare(strict_types=1);

// This file contains examples that should trigger the AccessModifierPresentedRule

class TestClass
{
    // Positive cases - should trigger the rule (missing explicit public modifier)

    // Method without explicit public modifier
    function publicMethodWithoutModifier(): void
    {
    }

    // Property without explicit public modifier
    var $publicPropertyWithoutModifier;

    // Constant without explicit public modifier (PHP 7.1+)
    const PUBLIC_CONSTANT_WITHOUT_MODIFIER = 'value';

    // Multiple properties without explicit public modifier
    var $anotherPublicProperty, $yetAnotherProperty;

    // Negative cases - should NOT trigger the rule

    // Method with explicit public modifier
    public function publicMethodWithModifier(): void
    {
    }

    // Property with explicit public modifier
    public $publicPropertyWithModifier;

    // Constant with explicit public modifier
    public const PUBLIC_CONSTANT_WITH_MODIFIER = 'value';

    // Private method (should not trigger)
    private function privateMethod(): void
    {
    }

    // Protected method (should not trigger)
    protected function protectedMethod(): void
    {
    }

    // Private property (should not trigger)
    private $privateProperty;

    // Protected property (should not trigger)
    protected $protectedProperty;

    // Private constant (should not trigger)
    private const PRIVATE_CONSTANT = 'value';

    // Protected constant (should not trigger)
    protected const PROTECTED_CONSTANT = 'value';
}

// Test with interface (if configured to analyze interfaces)
interface TestInterface
{
    // Interface methods are implicitly public, but should still be explicit
    function interfaceMethodWithoutModifier(): void;

    // Explicit public modifier in interface
    public function interfaceMethodWithModifier(): void;

    // Interface constants
    const INTERFACE_CONSTANT_WITHOUT_MODIFIER = 'value';
    public const INTERFACE_CONSTANT_WITH_MODIFIER = 'value';
}