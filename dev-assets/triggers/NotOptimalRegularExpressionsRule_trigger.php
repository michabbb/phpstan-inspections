<?php

declare(strict_types=1);

// Trigger script for NotOptimalRegularExpressionsRule
// This script contains various regex patterns that should trigger the rule

function testMissingDelimiters(): void
{
    $text = 'test string';

    // VIOLATION: Missing delimiters in preg_match
    preg_match('test', $text);

    // VIOLATION: Missing delimiters in preg_replace
    preg_replace('old', 'new', $text);

    // VIOLATION: Missing delimiters in preg_split
    preg_split(' ', $text);

    // CORRECT: Proper delimiters
    preg_match('/test/', $text);
    preg_replace('/old/', 'new', $text);
}

function testDeprecatedModifiers(): void
{
    $text = 'test string';

    // VIOLATION: Deprecated /e modifier
    preg_replace('/test/e', 'strtoupper("$1")', $text);
}

function testInvalidModifiers(): void
{
    $text = 'test string';

    // VIOLATION: Invalid modifier 'z'
    preg_match('/test/z', $text);

    // VIOLATION: Invalid modifier 'q'
    preg_replace('/old/q', 'new', $text);
}

function testUselessModifiers(): void
{
    $text = 'test123';

    // VIOLATION: /s modifier without dots in pattern
    preg_match('/test/s', $text);

    // VIOLATION: /i modifier without letters in pattern
    preg_match('/123/i', $text);

    // CORRECT: /s with dots
    preg_match('/te.t/s', $text);

    // CORRECT: /i with letters
    preg_match('/test/i', $text);
}

function testCharacterClassOptimizations(): void
{
    $text = 'test123';

    // VIOLATION: [0-9] should be \d
    preg_match('/[0-9]+/', $text);

    // VIOLATION: [^0-9] should be \D
    preg_match('/[^0-9]+/', $text);

    // VIOLATION: [a-zA-Z0-9_] should be \w
    preg_match('/[a-zA-Z0-9_]+/', $text);

    // CORRECT: Already optimized
    preg_match('/\d+/', $text);
    preg_match('/\D+/', $text);
    preg_match('/\w+/', $text);
}

function testPlainApiAlternatives(): void
{
    $text = 'Hello World';

    // VIOLATION: Exact match that can use strpos
    preg_match('/^Hello$/', $text);

    // VIOLATION: Substring search that can use strpos
    preg_match('/World/', $text);

    // VIOLATION: Case-insensitive exact match
    preg_match('/^hello$/i', $text);

    // VIOLATION: Case-insensitive substring search
    preg_match('/world/i', $text);

    // CORRECT: Complex patterns that need regex
    preg_match('/^H.*d$/', $text);
    preg_match('/\b\w+\b/', $text);
}

function testPregQuoteUsage(): void
{
    $userInput = 'user@example.com';

    // VIOLATION: Missing delimiter argument in preg_quote
    $pattern1 = '/^' . preg_quote($userInput) . '$/';

    // CORRECT: With delimiter specified
    $pattern2 = '/^' . preg_quote($userInput, '/') . '$/';

    // Test the patterns
    if (preg_match($pattern1, $userInput)) {
        echo "Pattern 1 matches\n";
    }

    if (preg_match($pattern2, $userInput)) {
        echo "Pattern 2 matches\n";
    }
}

function testVariousPregFunctions(): void
{
    $text = 'test data';
    $array = ['test1', 'test2'];

    // VIOLATION: Missing delimiters in preg_filter
    preg_filter('test', 'replacement', $array);

    // VIOLATION: Missing delimiters in preg_grep
    preg_grep('test', $array);

    // VIOLATION: Missing delimiters in preg_match_all
    preg_match_all('test', $text);

    // VIOLATION: Missing delimiters in preg_replace_callback
    preg_replace_callback('test', static fn($m) => strtoupper($m[0]), $text);

    // CORRECT: With proper delimiters
    preg_filter('/test/', 'replacement', $array);
    preg_grep('/test/', $array);
    preg_match_all('/test/', $text);
    preg_replace_callback('/test/', static fn($m) => strtoupper($m[0]), $text);
}

// Execute all test functions
testMissingDelimiters();
testDeprecatedModifiers();
testInvalidModifiers();
testUselessModifiers();
testCharacterClassOptimizations();
testPlainApiAlternatives();
testPregQuoteUsage();
testVariousPregFunctions();