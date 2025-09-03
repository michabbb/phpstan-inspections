<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\RegularExpressions;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects non-optimal regular expressions in preg_* functions.
 *
 * This rule identifies various issues with regular expressions used in preg_* functions:
 * - Missing regex delimiters
 * - Deprecated or invalid modifiers
 * - Useless modifiers that don't affect the regex behavior
 * - Character classes that can be simplified (e.g., [0-9] => \d)
 * - Plain string operations that can be replaced with faster alternatives
 * - Missing necessary modifiers for certain patterns
 *
 * @implements Rule<FuncCall>
 */
class NotOptimalRegularExpressionsRule implements Rule
{
    private const PREG_FUNCTIONS = [
        'preg_filter',
        'preg_grep',
        'preg_match_all',
        'preg_match',
        'preg_replace_callback',
        'preg_replace',
        'preg_split',
        'preg_quote',
    ];

    private const VALID_MODIFIERS = 'eimsuxADJSUX';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower($node->name->toString());
        if (!in_array($functionName, self::PREG_FUNCTIONS, true)) {
            return [];
        }

        $args = $node->getArgs();
        if (empty($args)) {
            return [];
        }

        // Get the regex pattern argument
        $patternArg = $args[0];
        $patternValue = $patternArg->value;

        if (!$patternValue instanceof String_) {
            return [];
        }

        $regex = $patternValue->value;
        if ($regex === '') {
            return [];
        }

        $errors = [];

        // Check for missing delimiters
        if ($functionName !== 'preg_quote') {
            $delimiterErrors = $this->checkMissingDelimiters($regex, $patternValue);
            $errors = array_merge($errors, $delimiterErrors);
        }

        // Parse regex with delimiters if present
        $parsed = $this->parseRegex($regex);
        if ($parsed === null) {
            return $errors;
        }

        [$pattern, $modifiers] = $parsed;

        // Check modifiers
        $modifierErrors = $this->checkModifiers($functionName, $modifiers, $pattern, $patternValue);
        $errors = array_merge($errors, $modifierErrors);

        // Check character class optimizations
        $charClassErrors = $this->checkCharacterClassOptimizations($pattern, $patternValue);
        $errors = array_merge($errors, $charClassErrors);

        // Check for plain API alternatives
        $apiErrors = $this->checkPlainApiAlternatives($functionName, $pattern, $modifiers, $patternValue);
        $errors = array_merge($errors, $apiErrors);

        return $errors;
    }

    /**
     * Check if regex is missing delimiters
     */
    private function checkMissingDelimiters(string $regex, String_ $node): array
    {
        $patterns = [
            '/^([^{<(\[])(.*)(\\1)([a-zA-Z]+)?$/s',  // Standard delimiters
            '/^(\\{)(.*)(\\})([a-zA-Z]+)?$/s',       // Curly braces
            '/^(<)(.*)(>)([a-zA-Z]+)?$/s',           // Angle brackets
            '/^(\\()(.*)(\\))([a-zA-Z]+)?$/s',       // Parentheses
            '/^(\\[)(.*)(\\])([a-zA-Z]+)?$/s',       // Square brackets
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $regex)) {
                return []; // Has delimiters
            }
        }

        return [
            RuleErrorBuilder::message('The regular expression delimiters are missing (it should be e.g. \'/<regex-here>/\').')
                ->identifier('regex.missingDelimiters')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Parse regex into pattern and modifiers
     */
    private function parseRegex(string $regex): ?array
    {
        $patterns = [
            '/^([^{<(\[])(.*)(\\1)([a-zA-Z]+)?$/s',  // Standard delimiters
            '/^(\\{)(.*)(\\})([a-zA-Z]+)?$/s',       // Curly braces
            '/^(<)(.*)(>)([a-zA-Z]+)?$/s',           // Angle brackets
            '/^(\\()(.*)(\\))([a-zA-Z]+)?$/s',       // Parentheses
            '/^(\\[)(.*)(\\])([a-zA-Z]+)?$/s',       // Square brackets
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $regex, $matches)) {
                $pattern = $matches[2];
                $modifiers = $matches[4] ?? '';
                return [$pattern, $modifiers];
            }
        }

        return null;
    }

    /**
     * Check regex modifiers for issues
     */
    private function checkModifiers(string $functionName, string $modifiers, string $pattern, String_ $node): array
    {
        $errors = [];

        // Check for deprecated /e modifier
        if (strpos($modifiers, 'e') !== false) {
            $errors[] = RuleErrorBuilder::message('The /e modifier is deprecated. Use preg_replace_callback() instead.')
                ->identifier('regex.deprecatedModifier')
                ->line($node->getStartLine())
                ->build();
        }

        // Check for invalid modifiers
        for ($i = 0; $i < strlen($modifiers); $i++) {
            $modifier = $modifiers[$i];
            if (strpos(self::VALID_MODIFIERS, $modifier) === false) {
                $errors[] = RuleErrorBuilder::message("Invalid regex modifier '$modifier'. Valid modifiers are: " . self::VALID_MODIFIERS)
                    ->identifier('regex.invalidModifier')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        // Check for useless modifiers
        if (strpos($modifiers, 's') !== false && !preg_match('/\\./', $pattern)) {
            $errors[] = RuleErrorBuilder::message('The /s modifier is useless without dots (.) in the pattern.')
                ->identifier('regex.uselessModifier')
                ->line($node->getStartLine())
                ->build();
        }

        if (strpos($modifiers, 'i') !== false && !preg_match('/[a-zA-Z]/', $pattern)) {
            $errors[] = RuleErrorBuilder::message('The /i modifier is useless without letters in the pattern.')
                ->identifier('regex.uselessModifier')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Check for character class optimizations
     */
    private function checkCharacterClassOptimizations(string $pattern, String_ $node): array
    {
        $errors = [];

        // [0-9] => \d
        if (preg_match('/\\[0-9\\]/', $pattern)) {
            $errors[] = RuleErrorBuilder::message('Use \\d instead of [0-9] for better performance and readability.')
                ->identifier('regex.characterClassOptimization')
                ->line($node->getStartLine())
                ->build();
        }

        // [^0-9] => \D
        if (preg_match('/\\[^0-9\\]/', $pattern)) {
            $errors[] = RuleErrorBuilder::message('Use \\D instead of [^0-9] for better performance and readability.')
                ->identifier('regex.characterClassOptimization')
                ->line($node->getStartLine())
                ->build();
        }

        // [a-zA-Z0-9_] => \w
        if (preg_match('/\\[a-zA-Z0-9_\\]/', $pattern)) {
            $errors[] = RuleErrorBuilder::message('Use \\w instead of [a-zA-Z0-9_] for better performance and readability.')
                ->identifier('regex.characterClassOptimization')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Check for plain API alternatives
     */
    private function checkPlainApiAlternatives(string $functionName, string $pattern, string $modifiers, String_ $node): array
    {
        $errors = [];

        // Only suggest strpos() alternatives for simple literal text patterns
        // This follows the Java implementation's validation logic
        
        // Check if pattern contains regex meta-characters that make strpos() inappropriate
        if ($this->containsRegexMetacharacters($pattern)) {
            return $errors; // Skip suggestion for complex regex patterns
        }

        // Pattern for simple text matching: ^(optional)(word chars, hyphens, or escaped single chars)$(optional)  
        if (preg_match('/^(\\^?)([\\w\\-]+|\\\\[.*+?])(\\$?)$/', $pattern, $matches)) {
            $startAnchor = $matches[1];
            $searchText = $matches[2];
            $endAnchor = $matches[3];
            $ignoreCase = strpos($modifiers, 'i') !== false;
            
            // Unescape simple characters for strpos()
            $searchText = $this->unescapeSimpleChars($searchText);

            if ($functionName === 'preg_match') {
                if ($startAnchor && $endAnchor) {
                    // /^text$/ => string comparison
                    $func = $ignoreCase ? 'strcasecmp' : '===';
                    $comparison = $ignoreCase 
                        ? "strcasecmp(\$haystack, '$searchText') === 0"
                        : "\"$searchText\" === \$haystack";
                    $errors[] = RuleErrorBuilder::message("Use $comparison instead of preg_match for exact string comparison.")
                        ->identifier('regex.plainApiAlternative')
                        ->line($node->getStartLine())
                        ->build();
                } elseif ($startAnchor && !$endAnchor) {
                    // /^text/ => strpos(...) === 0
                    $func = $ignoreCase ? 'stripos' : 'strpos';
                    $errors[] = RuleErrorBuilder::message("Use $func(\$haystack, '$searchText') === 0 instead of preg_match for prefix matching.")
                        ->identifier('regex.plainApiAlternative')
                        ->line($node->getStartLine())
                        ->build();
                } elseif (!$startAnchor && !$endAnchor) {
                    // /text/ => strpos(...) !== false
                    $func = $ignoreCase ? 'stripos' : 'strpos';
                    $errors[] = RuleErrorBuilder::message("Use $func(\$haystack, '$searchText') !== false instead of preg_match for substring search.")
                        ->identifier('regex.plainApiAlternative')
                        ->line($node->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * Check if pattern contains regex meta-characters that make it unsuitable for strpos()
     */
    private function containsRegexMetacharacters(string $pattern): bool
    {
        // Regex meta-characters that indicate complex patterns unsuitable for strpos():
        // \d, \D, \h, \H, \s, \S, \v, \V, \w, \W, \R, \b (word boundaries, character classes)
        // ., *, +, ?, {, }, [, ], (, ), |, etc.
        
        // Check for backslash followed by character class shortcuts
        if (preg_match('/\\\\[dDhHsSvVwWRb]/', $pattern)) {
            return true;
        }
        
        // Check for unescaped regex meta-characters (not preceded by backslash)
        if (preg_match('/[^\\\\][.\\*\\+\\?\\{\\}\\[\\]\\(\\)\\|]/', $pattern)) {
            return true;
        }
        
        // Check for meta-characters at the beginning of the pattern
        if (preg_match('/^[.\\*\\+\\?\\{\\}\\[\\]\\(\\)\\|]/', $pattern)) {
            return true;
        }
        
        return false;
    }

    /**
     * Unescape simple escaped characters for use in strpos()
     */
    private function unescapeSimpleChars(string $text): string
    {
        // Only unescape basic punctuation that might be escaped in regex but is literal in strings
        return preg_replace('/\\\\([.+*?\\-])/', '$1', $text);
    }
}