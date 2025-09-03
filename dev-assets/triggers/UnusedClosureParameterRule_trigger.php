<?php

declare(strict_types=1);

// Test cases for UnusedClosureParameterRule
// This file should trigger errors for unused closure parameters

// Positive cases - should trigger errors

function testUnusedParameter(): void
{
    $closure = function($unusedParam) {
        // $unusedParam is not used - should trigger error
        echo "Hello";
    };

    $closure("test");
}

function testMultipleUnusedParameters(): void
{
    $closure = function($param1, $param2, $param3) {
        // Only $param2 is used, $param1 and $param3 should trigger errors
        echo $param2;
    };

    $closure("a", "b", "c");
}

function testUnusedWithTypeHint(): void
{
    $closure = function(string $unusedString, int $usedInt): string {
        // $unusedString should trigger error, $usedInt is used
        return "Result: " . $usedInt;
    };

    $closure("test", 42);
}

function testUnusedInNestedClosure(): void
{
    $outerClosure = function($outerParam) {
        $innerClosure = function($innerParam) {
            // Both parameters are unused - should trigger errors
            return "nested";
        };

        return $innerClosure;
    };

    $outerClosure("outer");
}

// Negative cases - should NOT trigger errors

function testUsedParameter(): void
{
    $closure = function($usedParam) {
        // $usedParam is used - should NOT trigger error
        echo $usedParam;
    };

    $closure("test");
}

function testUnderscoreParameter(): void
{
    $closure = function($_unused) {
        // Parameters starting with _ should be ignored
        echo "Hello";
    };

    $closure("test");
}

function testMultipleUnderscoreParameters(): void
{
    $closure = function($_param1, $param2, $_param3) {
        // Only $param2 is used, underscore params should be ignored
        echo $param2;
    };

    $closure("a", "b", "c");
}

function testMixedUsage(): void
{
    $closure = function($used, $unused, $_ignored) {
        // $used is used, $unused should trigger, $_ignored is ignored
        return $used;
    };

    $closure("keep", "discard", "ignore");
}

function testClosureWithUse(): void
{
    $externalVar = "external";

    $closure = function($param) use ($externalVar) {
        // $param is unused but use variable is used - should trigger for $param
        echo $externalVar;
    };

    $closure("unused");
}