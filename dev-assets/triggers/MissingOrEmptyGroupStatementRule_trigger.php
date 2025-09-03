<?php

declare(strict_types=1);

// Positive cases - should trigger errors

// Missing braces in if statement
if ($condition)
    echo "test";

// Missing braces in elseif statement
if ($condition1) {
    echo "test1";
} elseif ($condition2)
    echo "test2";

// Missing braces in else statement
if ($condition1) {
    echo "test1";
} else
    echo "test2";

// Missing braces in foreach statement
foreach ($array as $item)
    echo $item;

// Missing braces in for statement
for ($i = 0; $i < 10; $i++)
    echo $i;

// Missing braces in while statement
while ($condition)
    echo "test";

// Missing braces in do-while statement
do
    echo "test";
while ($condition);

// Empty braces in if statement (should trigger if reportEmptyBody is true)
if ($condition) {
}

// Empty braces in foreach statement
foreach ($array as $item) {
}

// Empty braces in for statement
for ($i = 0; $i < 10; $i++) {
}

// Empty braces in while statement
while ($condition) {
}

// Empty braces in do-while statement
do {
} while ($condition);

// Negative cases - should NOT trigger errors

// Proper braces with content
if ($condition) {
    echo "test";
}

// Proper braces with content in foreach
foreach ($array as $item) {
    echo $item;
}

// Proper braces with content in for
for ($i = 0; $i < 10; $i++) {
    echo $i;
}

// Proper braces with content in while
while ($condition) {
    echo "test";
}

// Proper braces with content in do-while
do {
    echo "test";
} while ($condition);

// Else if construction - should NOT trigger missing braces error
if ($condition1) {
    echo "test1";
} elseif ($condition2) {
    echo "test2";
} else {
    echo "test3";
}