<?php
declare(strict_types=1);

// Test cases for SenselessMethodDuplicationRule

// Base parent class
abstract class BaseParent
{
    public function publicMethod(string $param1, int $param2): string
    {
        return "Base: $param1 - $param2";
    }
    
    protected function protectedMethod(array $data): array
    {
        return array_filter($data);
    }
    
    private function privateMethod(): string
    {
        return "private base";
    }
    
    abstract public function abstractMethod(): void;
}

// Parent class extending the base
class ParentClass extends BaseParent
{
    public function publicMethod(string $param1, int $param2): string
    {
        return "Parent: $param1 - $param2";
    }
    
    protected function protectedMethod(array $data): array
    {
        return array_unique($data);
    }
    
    public function abstractMethod(): void
    {
        echo "Parent implementation";
    }
    
    public function newParentMethod($value): string
    {
        return "new: $value";
    }
    
    protected function anotherParentMethod(): int
    {
        return 42;
    }
}

// Child class with various duplication scenarios
class Child extends ParentClass
{
    // Case 1: Identical public method with same access level (should trigger 'drop' error)
    public function publicMethod(string $param1, int $param2): string
    {
        return parent::publicMethod($param1, $param2);
    }
    
    // Case 2: Identical protected method with same access level (should trigger 'drop' error)
    protected function protectedMethod(array $data): array
    {
        return parent::protectedMethod($data);
    }
    
    // Case 3: Simple proxy method with different access level (should trigger 'proxy' error)
    public function anotherParentMethod(): int
    {
        return parent::anotherParentMethod();
    }
    
    // Case 4: Method with different implementation (should NOT trigger)
    public function newParentMethod($value): string
    {
        return "Child: $value (modified)";
    }
    
    // Case 5: Method with additional logic (should NOT trigger)
    public function abstractMethod(): void
    {
        parent::abstractMethod();
        echo " with child addition";
    }
    
    // Case 6: Private method (should NOT trigger - private methods are skipped)
    private function privateChildMethod(): void
    {
        // This won't trigger regardless of content
    }
    
    // Case 7: Method that doesn't exist in parent (should NOT trigger)
    public function uniqueChildMethod(): string
    {
        return "unique to child";
    }
}

// Another child with more complex scenarios
class AnotherChild extends ParentClass
{
    // Case 8: Proxy method with same parameters in different order (should NOT trigger)
    public function publicMethod(string $param1, int $param2): string
    {
        return parent::publicMethod($param2, $param1); // Wrong parameter order
    }
    
    // Case 9: Proxy method with fewer parameters (should NOT trigger)
    protected function protectedMethod(array $data): array
    {
        return parent::protectedMethod(); // Missing parameter
    }
}

// Class with complex method (testing size limit)
class ComplexChild extends ParentClass
{
    // Case 10: Method with many expressions (should NOT trigger due to size limit)
    public function complexMethod(): array
    {
        $result = [];
        $result['a'] = 1;
        $result['b'] = 2;
        $result['c'] = 3;
        $result['d'] = 4;
        $result['e'] = 5;
        $result['f'] = 6;
        $result['g'] = 7;
        $result['h'] = 8;
        $result['i'] = 9;
        $result['j'] = 10;
        $result['k'] = 11;
        $result['l'] = 12;
        $result['m'] = 13;
        $result['n'] = 14;
        $result['o'] = 15;
        $result['p'] = 16;
        $result['q'] = 17;
        $result['r'] = 18;
        $result['s'] = 19;
        $result['t'] = 20;
        $result['u'] = 21; // This should exceed the MAX_METHOD_SIZE limit
        return $result;
    }
}

// Interface case (should be skipped)
interface TestInterface
{
    public function interfaceMethod(): void;
}

// Trait case (should be skipped) 
trait TestTrait
{
    public function traitMethod(): string
    {
        return parent::traitMethod();
    }
}

// Final class to prevent further extension
final class FinalChild extends ParentClass
{
    // Case 11: Simple proxy in final class (should still trigger)
    public function publicMethod(string $param1, int $param2): string
    {
        return parent::publicMethod($param1, $param2);
    }
}

// Static method scenario
class StaticParent
{
    public static function staticMethod(int $value): int
    {
        return $value * 2;
    }
}

class StaticChild extends StaticParent
{
    // Case 12: Static method proxy (should trigger if detected)
    public static function staticMethod(int $value): int
    {
        return parent::staticMethod($value);
    }
}