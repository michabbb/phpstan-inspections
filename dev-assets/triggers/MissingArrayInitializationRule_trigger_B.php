<?php
declare(strict_types=1);

// Positive case: Missing array initialization in nested loops
// This should trigger the MissingArrayInitializationRule

function processData(array $data): array
{
    $result = [];

    foreach ($data as $item) {
        for ($i = 0; $i < 10; $i++) {
            // This should trigger: $result[] in nested loops without initialization
            $result[] = $item * $i;
        }
    }

    return $result;
}

function anotherExample(): void
{
    for ($x = 0; $x < 5; $x++) {
        while ($x < 3) {
            // This should also trigger: $output[] in deeply nested loops
            $output[] = $x;
            $x++;
        }
    }
}