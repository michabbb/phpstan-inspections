<?php

class MyTestClass
{
    private const MY_CONSTANT = 'value';

    private static string $myProperty = 'test';

    public function testMethod(): void
    {
        // These should NOT trigger the rule (already using 'self')
        $instance = new self(); // Correct usage
        $constant = self::MY_CONSTANT; // Correct usage
        $property = self::$myProperty; // Correct usage
        $className = __CLASS__; // Correct usage

        // Static method call with self
        self::staticMethod(); // Correct usage

        // Using different class names should not trigger
        $otherInstance = new OtherClass();
        $otherConstant = OtherClass::OTHER_CONSTANT;
    }

    public static function staticMethod(): string
    {
        return 'test';
    }
}

class OtherClass
{
    public const OTHER_CONSTANT = 'other';
}