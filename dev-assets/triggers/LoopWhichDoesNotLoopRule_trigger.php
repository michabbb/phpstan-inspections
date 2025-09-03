<?php
declare(strict_types=1);

/**
 * Trigger file for LoopWhichDoesNotLoopRule
 * This file contains various loop constructs that should trigger the rule
 */

// Empty for loop - should trigger
for ($i = 0; $i < 10; $i++) {
}

// Empty foreach loop - should trigger
$array = [1, 2, 3];
foreach ($array as $item) {
}

// Empty while loop - should trigger
$i = 0;
while ($i < 10) {
}

// Empty do-while loop - should trigger
$i = 0;
do {
} while ($i < 10);

// For loop with immediate break - should trigger
for ($i = 0; $i < 10; $i++) {
    break;
}

// Foreach loop with immediate break - should trigger
foreach ($array as $item) {
    break;
}

// While loop with immediate break - should trigger
$i = 0;
while ($i < 10) {
    break;
}

// Do-while loop with immediate break - should trigger
$i = 0;
do {
    break;
} while ($i < 10);

// For loop with immediate return - should trigger
function testReturnInFor() {
    for ($i = 0; $i < 10; $i++) {
        return;
    }
}

// Foreach loop with immediate return - should trigger
function testReturnInForeach() {
    foreach ($array as $item) {
        return;
    }
}

// While loop with immediate return - should trigger
function testReturnInWhile() {
    $i = 0;
    while ($i < 10) {
        return;
    }
}

// Do-while loop with immediate return - should trigger
function testReturnInDoWhile() {
    $i = 0;
    do {
        return;
    } while ($i < 10);
}

// For loop with immediate throw - should trigger
function testThrowInFor() {
    for ($i = 0; $i < 10; $i++) {
        throw new Exception('test');
    }
}

// Foreach loop with immediate throw - should trigger
function testThrowInForeach() {
    foreach ($array as $item) {
        throw new Exception('test');
    }
}

// While loop with immediate throw - should trigger
function testThrowInWhile() {
    $i = 0;
    while ($i < 10) {
        throw new Exception('test');
    }
}

// Do-while loop with immediate throw - should trigger
function testThrowInDoWhile() {
    $i = 0;
    do {
        throw new Exception('test');
    } while ($i < 10);
}

// For loop with no continue - should trigger
for ($i = 0; $i < 10; $i++) {
    echo $i;
}

// Foreach loop with no continue - should trigger
foreach ($array as $item) {
    echo $item;
}

// While loop with no continue - should trigger
$i = 0;
while ($i < 10) {
    echo $i;
    $i++;
}

// Do-while loop with no continue - should trigger
$i = 0;
do {
    echo $i;
    $i++;
} while ($i < 10);

// These should NOT trigger the rule:

// Foreach over generator - should NOT trigger
function generatorFunction() {
    yield 1;
    yield 2;
    yield 3;
}

foreach (generatorFunction() as $value) {
    echo $value;
}

// Foreach over Traversable - should NOT trigger
class MyTraversable implements Traversable {
    public function getIterator(): Iterator {
        return new ArrayIterator([1, 2, 3]);
    }
}

$traversable = new MyTraversable();
foreach ($traversable as $value) {
    echo $value;
}

// Foreach over Iterator - should NOT trigger
$iterator = new ArrayIterator([1, 2, 3]);
foreach ($iterator as $value) {
    echo $value;
}

// Foreach over array (iterable) - should NOT trigger
$array2 = [1, 2, 3];
foreach ($array2 as $value) {
    echo $value;
}

// Loops with continue statements - should NOT trigger
for ($i = 0; $i < 10; $i++) {
    if ($i % 2 === 0) {
        continue;
    }
    echo $i;
}

foreach ($array as $item) {
    if ($item % 2 === 0) {
        continue;
    }
    echo $item;
}

$i = 0;
while ($i < 10) {
    $i++;
    if ($i % 2 === 0) {
        continue;
    }
    echo $i;
}

$i = 0;
do {
    $i++;
    if ($i % 2 === 0) {
        continue;
    }
    echo $i;
} while ($i < 10);