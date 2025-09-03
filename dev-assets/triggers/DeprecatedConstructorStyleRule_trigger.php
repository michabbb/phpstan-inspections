<?php declare(strict_types=1);

namespace App\Test;

// This class should trigger the rule (PHP4-style constructor)
class MyOldClass
{
    public function MyOldClass()
    {
        // Constructor logic
    }
}

// This class should NOT trigger the rule (modern constructor)
class MyNewClass
{
    public function __construct()
    {
        // Constructor logic
    }
}

// This class should NOT trigger the rule (no constructor, no method with class name)
class MyOtherClass
{
    public function someMethod()
    {
        // Some logic
    }
}

// This class should NOT trigger the rule (static method with class name)
class MyStaticClass
{
    public static function MyStaticClass()
    {
        // Constructor logic
    }
}

// This class should NOT trigger the rule (interface)
interface MyInterface
{
    public function MyInterface();
}

// This class should NOT trigger the rule (trait)
trait MyTrait
{
    public function MyTrait()
    {
        // Trait logic
    }
}
