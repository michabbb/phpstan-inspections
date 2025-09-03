<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PhpParser\PrettyPrinter\Standard;

/**
 * Detects when strtr() is used where str_replace() would be more appropriate.
 *
 * This rule identifies cases where strtr() is called with a single character or short string
 * literal as the search parameter. In such cases, str_replace() clarifies intention and
 * improves maintainability by being more explicit about the replacement operation.
 *
 * The rule detects patterns like:
 * - strtr($string, '\\', '/') → str_replace('\\', '/', $string)
 * - strtr($text, '"', "'") → str_replace('"', "'", $text)
 *
 * @implements Rule<FuncCall>
 */
final class StrTrUsageAsStrReplaceRule implements Rule
{
    private Standard $prettyPrinter;

    public function __construct()
    {
        $this->prettyPrinter = new Standard();
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        // Only for global function strtr
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower($node->name->toString());
        if ($functionName !== 'strtr') {
            return [];
        }

        // Must have exactly 3 arguments
        if (count($node->args) !== 3) {
            return [];
        }

        // Second argument must be a string literal
        $searchArg = $node->args[1] ?? null;
        if (!$searchArg instanceof Arg || !$searchArg->value instanceof String_) {
            return [];
        }

        $searchLiteral = $searchArg->value;
        $searchContent = $searchLiteral->value;

        // String must not be empty and length <= 2
        if ($searchContent === '' || strlen($searchContent) > 2) {
            return [];
        }

        // Check if the string matches the allowed patterns
        if (!$this->matchesAllowedPattern($searchContent)) {
            return [];
        }

        // Build the suggested replacement
        $subject = $this->prettyPrinter->prettyPrintExpr($node->args[0]->value);
        $search = $this->prettyPrinter->prettyPrintExpr($searchLiteral);
        $replace = $this->prettyPrinter->prettyPrintExpr($node->args[2]->value);

        $suggestedReplacement = sprintf('str_replace(%s, %s, %s)', $search, $replace, $subject);

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' would fit more here (clarifies intention, improves maintainability).", $suggestedReplacement)
            )
                ->identifier('string.strtrUsageAsStrReplace')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Check if the search string matches the allowed patterns from the Java inspector.
     * The patterns are for strings that would benefit from str_replace over strtr.
     */
    private function matchesAllowedPattern(string $content): bool
    {
        // Pattern from Java inspector: matches single chars or simple escape sequences
        // This covers: single char, escaped backslash, escaped quotes, escaped $rnt
        return preg_match('/^(.|\\\\[\\\\\'"$rnt])$/', $content) === 1;
    }
}