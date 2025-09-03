<?php

// Positive cases - should trigger the rule

// Simple string without special characters
$name = "John";

// String with spaces
$message = "Hello World";

// String with numbers
$version = "1.0.0";

// String with underscores
$class_name = "My_Class";

// Negative cases - should NOT trigger the rule

// String with single quotes (would need escaping)
$quote = "It's working";

// String with escape sequences
$path = "C:\\Users\\file.txt";

// String with variable interpolation
$greeting = "Hello $name";

// Heredoc (not supported by this rule)
$heredoc = <<<EOT
This is a heredoc
EOT;

// Nowdoc (not supported by this rule)
$nowdoc = <<<'EOT'
This is a nowdoc
EOT;

// Single quoted strings (already correct)
$single = 'This is fine';

// Empty string
$empty = "";

// String with backslash that would be problematic
$regex = "\\d+";

// String that contains both single quotes and would need escaping
$text = "Don't do \"this\"";

// Valid double quote usage with escape sequences
$json = "{\"key\": \"value\"}";

// Valid double quote usage with tabs/newlines
$multiline = "Line 1\nLine 2\tTabbed";