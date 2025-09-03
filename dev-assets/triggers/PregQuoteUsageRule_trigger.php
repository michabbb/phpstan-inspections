<?php

declare(strict_types=1);

// Examples demonstrating proper and improper preg_quote() usage

function demonstratePregQuoteUsage(): void
{
    $userInput = 'user@example.com';
    
    // VIOLATION: Missing delimiter argument - should trigger PHPStan error
    $pattern1 = '/^' . preg_quote($userInput) . '$/';
    
    // VIOLATION: Another missing delimiter case
    $escaped  = preg_quote($userInput);
    $pattern2 = '#' . $escaped . '#i';
    
    // CORRECT: With delimiter specified
    $pattern3 = '/^' . preg_quote($userInput, '/') . '$/';
    
    // CORRECT: With hash delimiter
    $pattern4 = '#^' . preg_quote($userInput, '#') . '$#i';
    
    // CORRECT: With tilde delimiter
    $pattern5 = '~' . preg_quote($userInput, '~') . '~';
    
    // CORRECT: With at symbol delimiter
    $pattern6 = '@' . preg_quote($userInput, '@') . '@';
    
    // Test the patterns (this won't trigger our rule)
    if (preg_match($pattern1, $userInput)) {
        echo "Pattern 1 matches\n";
    }
    
    if (preg_match($pattern3, $userInput)) {
        echo "Pattern 3 matches\n";
    }
}

// More examples with different contexts
function moreExamples(): void
{
    $search = 'test@domain.com';
    
    // VIOLATION: preg_quote without delimiter in array context
    $patterns = [
        'email'  => '/^' . preg_quote($search) . '$/',
        'domain' => '#^' . preg_quote($search, '#') . '$#', // CORRECT
    ];
    
    // VIOLATION: In variable assignment
    $quotedSearch = preg_quote($search);
    
    // CORRECT: Proper usage
    $properlyQuoted = preg_quote($search, '/');
}

demonstratePregQuoteUsage();
moreExamples();
