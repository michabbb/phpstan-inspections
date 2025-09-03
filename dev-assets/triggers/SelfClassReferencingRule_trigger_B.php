<?php

class MyTestClass
{
    private const MY_CONSTANT = 'value';

    private static string $myProperty = 'test';

    public function testMethod(): void
    {
        // These should trigger the rule
        $instance = new MyTestClass(); // Should suggest 'self'
        $constant = MyTestClass::MY_CONSTANT; // Should suggest 'self::MY_CONSTANT'
        $property = MyTestClass::$myProperty; // Should suggest 'self::$myProperty'
        $className = MyTestClass::class; // Should suggest '__CLASS__'

        // Static method call
        MyTestClass::staticMethod(); // Should suggest 'self::staticMethod()'
    }

    public static function staticMethod(): string
    {
        return 'test';
    }
}