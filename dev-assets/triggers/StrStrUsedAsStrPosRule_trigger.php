<?php declare(strict_types=1);

$haystack = 'hello world';
$needle = 'world';
$anotherNeedle = 'foo';

// Positive cases (should trigger the rule)

// Implicit boolean context
if (strstr($haystack, $needle)) {
    echo 'found';
}

if (!strstr($haystack, $anotherNeedle)) {
    echo 'not found';
}

$result = strstr($haystack, $needle) && true;

// Explicit comparison with false
if (strstr($haystack, $needle) === false) {
    echo 'not found';
}

if (stristr($haystack, $needle) != false) {
    echo 'found case insensitive';
}

$test = (strstr($haystack, $needle) == false) ? 'a' : 'b';

// Negative cases (should NOT trigger the rule)

// Used as a value, not in a boolean context
$foundString = strstr($haystack, $needle);
echo $foundString;

// Already using strpos/stripos correctly
if (strpos($haystack, $needle) !== false) {
    echo 'found with strpos';
}

// Not a strstr/stristr call
if (str_contains($haystack, $needle)) {
    echo 'found with str_contains';
}

// Less than 2 arguments
strstr($haystack);
