<?php

declare(strict_types=1);

// Positive cases - should trigger errors

class ParentTestClass {
    private string $property = 'test';

    public function nonStaticMethod(): void {
        echo "Non-static method";
    }

    public static function staticMethod(): void {
        echo "Static method";
    }
}

class TestClass extends ParentTestClass {
    public function testStaticClosureWithThis(): void {
        $closure = static function() {
            // This should trigger: '$this' can not be used in static closures.
            echo $this->property;
        };
    }

    public function testStaticClosureWithParentCall(): void {
        $closure = static function() {
            // This should trigger: Non-static method should not be used in static closures.
            parent::nonStaticMethod();
            echo "test"; // Add something to ensure the closure is analyzed
        };
    }

    public function testStaticClosureWithBoth(): void {
        $closure = static function() {
            // Both errors should be triggered
            echo $this->property;
            parent::nonStaticMethod();
        };
    }
}

// Negative cases - should NOT trigger errors

class AnotherTestClass extends ParentTestClass {
    public function testNonStaticClosure(): void {
        $closure = function() {
            // This should NOT trigger - closure is not static
            echo $this->property;
            parent::nonStaticMethod();
        };
    }

    public function testStaticClosureWithoutIssues(): void {
        $closure = static function() {
            // This should NOT trigger - no $this or parent calls
            $localVar = 'test';
            echo $localVar;
        };
    }

    public function testStaticClosureWithStaticParentCall(): void {
        $closure = static function() {
            // This should NOT trigger - parent::staticMethod is static
            parent::staticMethod();
        };
    }
}

// Edge cases

class EdgeCaseTest extends ParentTestClass {
    public function testNestedClosures(): void {
        $outerClosure = static function() {
            $innerClosure = function() {
                // This should NOT trigger - inner closure is not static
                echo $this->property;
            };
        };
    }

    public function testStaticClosureInNonClassContext(): void {
        // Static closure outside class context
        $closure = static function() {
            // This should NOT trigger - no $this available anyway
            $var = 'test';
            echo $var;
        };
    }
}

// Simple test case for parent method detection
class SimpleParentTest extends ParentTestClass {
    public function testSimpleParentCall(): void {
        $closure = static function() {
            // Simple parent call to test detection
            parent::nonStaticMethod();
        };
    }
}