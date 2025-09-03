<?php
declare(strict_types=1);

// Negative case: Properly initialized arrays or cases that should not trigger
// This should NOT trigger the MissingArrayInitializationRule

function processDataProperly(array $data): array
{
    $result = []; // Properly initialized

    foreach ($data as $item) {
        for ($i = 0; $i < 10; $i++) {
            // This should NOT trigger: array is initialized before use
            $result[] = $item * $i;
        }
    }

    return $result;
}

function withParameter(array $existingArray): void
{
    foreach ($existingArray as $item) {
        for ($i = 0; $i < 5; $i++) {
            // This should NOT trigger: using function parameter
            $existingArray[] = $item + $i;
        }
    }
}

function withForeach(): void
{
    $data = [1, 2, 3];

    foreach ($data as $item) {
        for ($i = 0; $i < 3; $i++) {
            // This should NOT trigger: array is used in foreach
            $data[] = $item * $i;
        }
    }
}

function singleLoop(): void
{
    $result = [];

    for ($i = 0; $i < 10; $i++) {
        // This should NOT trigger: only single loop level
        $result[] = $i;
    }
}

function closureWithUse(): void
{
    $externalArray = [];

    $closure = function() use (&$externalArray) {
        for ($i = 0; $i < 5; $i++) {
            while ($i < 3) {
                // This should NOT trigger: using use-variable
                $externalArray[] = $i;
                $i++;
            }
        }
    };

    $closure();
}