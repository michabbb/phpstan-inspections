<?php

declare(strict_types=1);

/**
 * Test cases for UnusedGotoLabelRule
 * This file contains examples of used and unused goto labels
 */

// Function with unused goto label - should trigger the rule
function testUnusedLabel(): void
{
    $x = 1;

    unused_label: // This label is never used by goto

    if ($x > 0) {
        echo "Positive\n";
    }
}

// Function with used goto label - should NOT trigger the rule
function testUsedLabel(): void
{
    $x = 1;

    start: // This label is used by goto

    if ($x > 0) {
        echo "Positive\n";
        goto start; // Uses the label
    }
}

// Function with multiple labels - some used, some unused
function testMixedLabels(): void
{
    $x = 1;

    first_label: // Used
    if ($x > 0) {
        echo "First\n";
        goto second_label; // Uses first_label indirectly
    }

    second_label: // Used
    if ($x < 10) {
        echo "Second\n";
        goto first_label; // Uses second_label indirectly
    }

    unused_label: // Unused - should trigger
    echo "This should not be reached\n";
}

// Label outside function - should NOT trigger (rule only checks within functions)
outside_label:
echo "Outside function\n";

// Class method with unused label
class TestClass
{
    public function testMethod(): void
    {
        $x = 1;

        method_label: // Unused - should trigger
        if ($x > 0) {
            echo "Method\n";
        }
    }

    public function testMethodWithUsedLabel(): void
    {
        $x = 1;

        method_used: // Used
        if ($x > 0) {
            echo "Method used\n";
            goto method_used; // Uses the label
        }
    }
}