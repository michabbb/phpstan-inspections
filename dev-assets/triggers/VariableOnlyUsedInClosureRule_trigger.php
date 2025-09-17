<?php

declare(strict_types=1);

// Test cases for VariableOnlyUsedInClosureRule
// This file should trigger errors for variables only used inside closures

// Positive cases - should trigger errors

function testBasicCase(): void
{
    $dryRunLabel = 'DRY-RUN'; // Should trigger error - only used in closure

    $items = [1, 2, 3];
    array_map(function($item) use ($dryRunLabel) {
        return $item . $dryRunLabel; // Only usage
    }, $items);
}

function testMultipleVariables(): void
{
    $prefix = 'PREFIX-'; // Should trigger error
    $suffix = 'SUFFIX'; // Should NOT trigger - used outside closure
    $unused = 'UNUSED'; // Should trigger error

    echo $suffix; // External usage

    $items = [1, 2, 3];
    array_map(function($item) use ($prefix, $unused) {
        return $prefix . $item . $unused; // Only usage for $prefix and $unused
    }, $items);
}

function testNestedClosures(): void
{
    $outerVar = 'OUTER'; // Should trigger error
    $innerVar = 'INNER'; // Should trigger error

    $outerClosure = function() use ($outerVar) {
        $innerClosure = function() use ($innerVar) {
            return $innerVar; // Only usage
        };
        return $outerVar . $innerClosure(); // Only usage
    };

    $outerClosure();
}

function testComplexCase(): void
{
    $config = ['key' => 'value']; // Should trigger error
    $debug = true; // Should NOT trigger - used outside closure

    if ($debug) {
        echo "Debug mode";
    }

    $closure = function($data) use ($config, $debug) {
        // Only $config is only used here, $debug was used above
        return array_merge($data, $config);
    };

    $closure(['test' => 1]);
}

// Negative cases - should NOT trigger errors

function testVariableUsedOutside(): void
{
    $label = 'LABEL'; // Should NOT trigger - used outside closure

    echo $label; // External usage

    $closure = function($item) use ($label) {
        return $item . $label;
    };

    $closure('test');
}

function testVariableNotInClosure(): void
{
    $value = 'VALUE'; // Should NOT trigger - not used in closure

    echo $value; // Only usage, not in closure
}

function testClosureWithoutUse(): void
{
    $external = 'EXTERNAL'; // Should NOT trigger - not used in closure

    $closure = function($item) {
        // No use clause
        return $item;
    };

    echo $external;
    $closure('test');
}

function testVariableModifiedOutside(): void
{
    $counter = 0; // Should NOT trigger - modified outside closure

    $counter++; // External modification

    $closure = function($item) use ($counter) {
        return $item . $counter;
    };

    $closure('test');
}

function testSelfReferenceInAssignment(): void
{
    $accumulator = []; // Should NOT trigger - self-reference

    $accumulator = array_merge($accumulator, ['item']); // Self-reference

    $closure = function($item) use ($accumulator) {
        return in_array($item, $accumulator);
    };

    $closure('test');
}

function testMethodCallOnVariable(): void
{
    $object = new \stdClass(); // Should NOT trigger - method call outside
    $object->property = 'value'; // External usage

    $closure = function($data) use ($object) {
        return $data . $object->property;
    };

    $closure('test');
}

class TestClass
{
    public function testMethodContext(): void
    {
        $methodVar = 'METHOD'; // Should trigger error

        $this->processItems(function($item) use ($methodVar) {
            return $item . $methodVar; // Only usage
        });
    }

    private function processItems(callable $callback): void
    {
        // Helper method
    }

    public function testPropertyAccess(): void
    {
        $localVar = 'LOCAL'; // Should NOT trigger - property access
        $this->property = $localVar; // External usage

        $closure = function($item) use ($localVar) {
            return $item . $localVar;
        };

        $closure('test');
    }

    private $property;
}

// Edge cases

function testMultipleUseClauses(): void
{
    $var1 = 'VAR1'; // Should trigger error
    $var2 = 'VAR2'; // Should NOT trigger - used in multiple places

    $closure1 = function($item) use ($var1, $var2) {
        return $item . $var1 . $var2;
    };

    $closure2 = function($item) use ($var2) {
        return $item . $var2; // $var2 used in multiple closures
    };

    $closure1('test');
    $closure2('test');
}

function testConditionalUsage(): void
{
    $conditionalVar = 'CONDITIONAL'; // Should NOT trigger - conditional usage

    if (random_int(0, 1)) {
        echo $conditionalVar; // Conditional external usage
    }

    $closure = function($item) use ($conditionalVar) {
        return $item . $conditionalVar;
    };

    $closure('test');
}