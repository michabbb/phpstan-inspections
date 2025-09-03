<?php declare(strict_types=1);

namespace IssetConstructsCanBeMergedTrigger;

// Test cases for IssetConstructsCanBeMergedRule

function testIssetAndIssetMerge(): void
{
    $data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];

    // This should trigger: isset && isset can be merged
    if (isset($data['key1']) && isset($data['key2'])) {
        echo "Both keys exist";
    }

    // This should trigger: multiple isset with &&
    if (isset($data['key1']) && isset($data['key2']) && isset($data['key3'])) {
        echo "All three keys exist";
    }

    // This should NOT trigger: different variables
    if (isset($data['key1']) && isset($_GET['param'])) {
        echo "Mixed isset calls";
    }

    // This should NOT trigger: single isset
    if (isset($data['key1'])) {
        echo "Single isset";
    }
}

function testNotIssetOrNotIssetMerge(): void
{
    $data = ['key1' => 'value1', 'key2' => 'value2'];

    // This should trigger: !isset || !isset can be merged
    if (!isset($data['key1']) || !isset($data['key2'])) {
        echo "At least one key is missing";
    }

    // This should trigger: multiple !isset with ||
    if (!isset($data['key1']) || !isset($data['key2']) || !isset($data['key3'])) {
        echo "At least one of three keys is missing";
    }

    // This should NOT trigger: different variables
    if (!isset($data['key1']) || !isset($_GET['param'])) {
        echo "Mixed !isset calls";
    }

    // This should NOT trigger: single !isset
    if (!isset($data['key1'])) {
        echo "Single !isset";
    }
}

function testComplexExpressions(): void
{
    $data = ['a' => 1, 'b' => 2, 'c' => 3];

    // This should trigger: isset && isset in complex expression
    if ((isset($data['a']) && isset($data['b'])) || isset($data['c'])) {
        echo "Complex expression with mergeable isset";
    }

    // This should trigger: !isset || !isset in complex expression
    if ((!isset($data['a']) || !isset($data['b'])) && isset($data['c'])) {
        echo "Complex expression with mergeable !isset";
    }
}

function testNestedIssetCalls(): void
{
    $nested = ['level1' => ['level2' => 'value']];

    // This should trigger: nested isset calls with &&
    if (isset($nested['level1']['level2']) && isset($nested['level1'])) {
        echo "Nested isset calls";
    }
}

class TestClass
{
    public function testMethod(): void
    {
        $data = ['prop1' => 'value1', 'prop2' => 'value2'];

        // This should trigger: isset && isset in method
        if (isset($data['prop1']) && isset($data['prop2'])) {
            echo "Method isset merge";
        }

        // This should trigger: !isset || !isset in method
        if (!isset($data['prop1']) || !isset($data['prop2'])) {
            echo "Method !isset merge";
        }
    }
}