<?php

declare(strict_types=1);

// Test class with various method types for callable validation
class TestCallableClass
{
    public static function publicStaticMethod(): void {}
    public function publicInstanceMethod(): void {}
    private static function privateStaticMethod(): void {}
    private function privateInstanceMethod(): void {}
    protected static function protectedStaticMethod(): void {}
    protected function protectedInstanceMethod(): void {}
}

// Valid cases - should not trigger errors
$valid1 = is_callable('TestCallableClass::publicStaticMethod'); // OK - static and public
$valid2 = is_callable(['TestCallableClass', 'publicInstanceMethod']); // OK - instance method for object callable
$valid3 = is_callable([new TestCallableClass(), 'publicInstanceMethod']); // OK - instance method for object callable

// Invalid cases - should trigger errors

// ERROR: Method should be static but is instance
$error1 = is_callable('TestCallableClass::publicInstanceMethod');

// ERROR: Method should be static but is instance (string class reference)
$error2 = is_callable(['TestCallableClass', 'publicInstanceMethod']);

// ERROR: Method is not public (private)
$error3 = is_callable('TestCallableClass::privateStaticMethod');

// ERROR: Method is not public (protected)
$error4 = is_callable('TestCallableClass::protectedStaticMethod');

// ERROR: Method is not public and not static
$error5 = is_callable(['TestCallableClass', 'privateInstanceMethod']);

// Using class constant - should require static method
$error6 = is_callable([TestCallableClass::class, 'publicInstanceMethod']);

// Valid case with class constant
$valid4 = is_callable([TestCallableClass::class, 'publicStaticMethod']);

// Test with variable containing class name
$className = 'TestCallableClass';
$error7 = is_callable([$className, 'publicInstanceMethod']); // Should be static
$valid5 = is_callable([$className, 'publicStaticMethod']); // OK