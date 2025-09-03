<?php

declare(strict_types=1);

// Test file for SlowArrayOperationsInLoopRule

// POSITIVE CASES - Should trigger errors

// 1. Greedy array_merge in loop - should trigger error
function testArrayMergeInLoop(): void
{
    $options = [];
    $configurationSources = [['a'], ['b'], ['c']];
    
    foreach ($configurationSources as $source) {
        $options = array_merge($options, $source); // ERROR: Greedy operation in loop
    }
}

// 2. Greedy array_merge_recursive in loop - should trigger error  
function testArrayMergeRecursiveInLoop(): void
{
    $data = [];
    $sources = [['key1' => ['nested' => 'value1']], ['key2' => ['nested' => 'value2']]];
    
    for ($i = 0; $i < count($sources); ++$i) {
        $data = array_merge_recursive($data, $sources[$i]); // ERROR: Greedy operation in loop
    }
}

// 3. count() in for loop condition - should trigger error
function testCountInForLoopCondition(): void
{
    $array = [1, 2, 3, 4, 5];
    
    for ($index = 0; $index < count($array); ++$index) { // ERROR: Slow function in loop
        echo $array[$index];
    }
}

// 4. strlen() in for loop condition - should trigger error
function testStrlenInForLoopCondition(): void
{
    $text = "Hello World";
    
    for ($i = 0; $i < strlen($text); ++$i) { // ERROR: Slow function in loop  
        echo $text[$i];
    }
}

// 5. array_replace in while loop - should trigger error
function testArrayReplaceInWhileLoop(): void
{
    $base = ['a' => 1, 'b' => 2];
    $updates = [['a' => 2], ['b' => 3], ['c' => 4]];
    $i = 0;
    
    while ($i < count($updates)) {
        $base = array_replace($base, $updates[$i]); // ERROR: Greedy operation in loop
        $i++;
    }
}

// NEGATIVE CASES - Should NOT trigger errors

// 6. Array collection pattern (good) - should NOT trigger error
function testArrayCollectionPattern(): void
{
    $options = [];
    $configurationSources = [['a'], ['b'], ['c']];
    
    foreach ($configurationSources as $source) {
        $options[] = $source; // Good: collecting, not merging in loop
    }
    
    $options = array_merge(...$options); // Good: merge once outside loop
}

// 7. Cached count in for loop (good) - should NOT trigger error
function testCachedCountInForLoop(): void
{
    $array = [1, 2, 3, 4, 5];
    
    for ($index = 0, $count = count($array); $index < $count; ++$index) { // Good: cached count
        echo $array[$index];
    }
}

// 8. array_merge without self-assignment - should NOT trigger error
function testArrayMergeWithoutSelfAssignment(): void
{
    $base = ['a'];
    $sources = [['b'], ['c']];
    
    foreach ($sources as $source) {
        $result = array_merge($base, $source); // Good: not assigning back to $base
        echo implode(',', $result);
    }
}

// 9. count() outside of loop condition - should NOT trigger error
function testCountOutsideLoop(): void
{
    $array = [1, 2, 3];
    $count = count($array); // Good: not in loop condition
    
    for ($i = 0; $i < $count; ++$i) {
        echo $array[$i];
    }
}

// 10. Non-greedy function in loop - should NOT trigger error
function testNonGreedyFunctionInLoop(): void
{
    $data = [];
    $sources = [[1], [2], [3]];
    
    foreach ($sources as $source) {
        $data = array_push($data, ...$source); // Good: array_push is not greedy
    }
}