<?php declare(strict_types=1);

// Test cases for StaticInvocationViaThisRule
// This rule detects static method calls using -> instead of ::

class TestStaticInvocationViaThis
{
    public static function staticMethod(): string
    {
        return 'static result';
    }

    public function instanceMethod(): string
    {
        return 'instance result';
    }

    // Positive cases - should trigger errors

    public function testThisStaticCall(): void
    {
        // This should trigger: $this->staticMethod() should be self::staticMethod()
        $result = $this->staticMethod();
    }

    public function testExpressionStaticCall(): void
    {
        $obj = new self();

        // This should trigger: $obj->staticMethod() should be self::staticMethod()
        $result = $obj->staticMethod();
    }

    public function testVariableStaticCall(): void
    {
        $instance = new TestStaticInvocationViaThis();

        // This should trigger: $instance->staticMethod() should be TestStaticInvocationViaThis::staticMethod()
        $result = $instance->staticMethod();
    }

    // Negative cases - should NOT trigger errors

    public function testInstanceMethodCall(): void
    {
        // This should NOT trigger - instance method called correctly
        $result = $this->instanceMethod();
    }

    public function testParameterStaticCall($param): void
    {
        // This should NOT trigger - $param is a parameter
        $result = $param->staticMethod();
    }

    public function testUseVariableStaticCall(): void
    {
        $externalVar = new self();

        $closure = function() use ($externalVar) {
            // This should NOT trigger - $externalVar is a use variable
            return $externalVar->staticMethod();
        };
    }

    public function testSelfStaticCall(): void
    {
        // This should NOT trigger - correct static call syntax
        $result = self::staticMethod();
    }

    public function testClassNameStaticCall(): void
    {
        // This should NOT trigger - correct static call syntax
        $result = TestStaticInvocationViaThis::staticMethod();
    }
}

// Test with PHPUnit-style assertions (should be skipped)
class PHPUnitTestExample
{
    public function testWithPHPUnit(): void
    {
        // This should NOT trigger - PHPUnit assertions are skipped
        $this->assertEquals('expected', 'actual');
    }
}

// Test with Eloquent-style methods (should be skipped)
class EloquentTestExample
{
    public function testWithEloquent(): void
    {
        // This should NOT trigger - Eloquent methods are skipped
        $this->where('id', 1)->get();
    }
}

// Edge cases
class EdgeCaseTests
{
    public static function anotherStaticMethod(): void
    {
        // Static method calling another static method
    }

    public function testStaticCallInStaticMethod(): void
    {
        // This should trigger even in static context
        $result = $this->anotherStaticMethod();
    }

    public function testChainedStaticCalls(): void
    {
        $obj = new self();

        // This should trigger - chained static call
        $result = $obj->staticMethod();
    }
}