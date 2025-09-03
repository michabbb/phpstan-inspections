<?php declare(strict_types=1);

/**
 * Trigger script for SuspiciousLoopRule testing
 * This file contains various loop patterns that should trigger the rule
 */

class TestClass
{
    public function testMultipleConditions(int $i, int $max): void
    {
        // This should trigger: multiple conditions in for loop
        for ($i = 0; $i < 10, $i < $max; $i++) {
            echo $i;
        }
    }

    public function testParameterOverride(int $item): void
    {
        // This should trigger: loop variable overrides parameter
        foreach ([1, 2, 3] as $item) {
            echo $item;
        }
    }

    public function testOuterLoopOverride(): void
    {
        // This should trigger: inner loop variable overrides outer loop variable
        for ($i = 0; $i < 5; $i++) {
            for ($i = 0; $i < 3; $i++) {
                echo $i;
            }
        }
    }

    public function testValidLoops(): void
    {
        // These should NOT trigger the rule
        for ($i = 0; $i < 10; $i++) {
            echo $i;
        }

        foreach ([1, 2, 3] as $value) {
            echo $value;
        }

        for ($j = 0; $j < 5; $j++) {
            for ($k = 0; $k < 3; $k++) {
                echo $j . $k;
            }
        }
    }

    public function testMultipleConditionsWithAnd(int $i, int $max): void
    {
        // This should NOT trigger: using && operator
        for ($i = 0; $i < 10 && $i < $max; $i++) {
            echo $i;
        }
    }
}

function testFunctionParameterOverride(string $key): void
{
    // This should trigger: loop variable overrides function parameter
    foreach (['a' => 1, 'b' => 2] as $key => $value) {
        echo $key . $value;
    }
}

function testValidFunction(): void
{
    // This should NOT trigger
    for ($i = 0; $i < 10; $i++) {
        echo $i;
    }
}