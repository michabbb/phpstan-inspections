<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unnecessary double quotes in string literals and suggests using single quotes instead.
 *
 * This rule identifies string literals that:
 * - Are enclosed in double quotes but don't contain single quotes
 * - Don't contain escape sequences that require double quotes
 * - Are not heredoc/nowdoc strings
 * - Are not inside PHPDoc comments
 *
 * The rule promotes code style consistency by preferring single quotes for simple strings
 * that don't require double quote features like variable interpolation or escape sequences.
 *
 * @implements Rule<String_>
 */
final class UnNecessaryDoubleQuotesRule implements Rule
{
    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof String_) {
            return [];
        }

        // Check the string kind
        $kind = $node->getAttribute('kind');

        // Skip heredoc and nowdoc
        if ($kind === 3 || $kind === 4) {
            return [];
        }

        // Only process double-quoted strings
        if ($kind !== 2) {
            return [];
        }

        // Skip if inside PHPDoc comment
        if ($this->isInsidePHPDoc($node)) {
            return [];
        }

        $content = $node->value;

        // Skip if content contains single quotes (would require escaping)
        if (str_contains($content, "'")) {
            return [];
        }

        // Get the raw token to check for escape sequences in source code
        $rawValue = $node->getAttribute('rawValue');
        if ($rawValue !== null) {
            // Check if raw source contains escape sequences
            if (str_contains($rawValue, '\\')) {
                return [];
            }
        }

        // Check for variable interpolation ($variable or ${variable})
        if (preg_match('/\$[a-zA-Z_][a-zA-Z0-9_]*|\$\{[^{}]+\}/', $content)) {
            return [];
        }

        // Skip empty strings
        if (strlen($content) === 0) {
            return [];
        }

        // If we get here, the double quotes are unnecessary
        return [
            RuleErrorBuilder::message('Safely use single quotes instead.')
                ->identifier('string.unnecessaryDoubleQuotes')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isInsidePHPDoc(String_ $node): bool
    {
        $parent = $node->getAttribute('parent');

        // Walk up the parent chain to find if we're inside a PHPDoc comment
        while ($parent !== null) {
            if ($parent instanceof \PhpParser\Node\Stmt\Class_ ||
                $parent instanceof \PhpParser\Node\Stmt\Function_ ||
                $parent instanceof \PhpParser\Node\Stmt\ClassMethod ||
                $parent instanceof \PhpParser\Node\Stmt\Function_ ||
                $parent instanceof \PhpParser\Node\Expr\Closure) {
                // Check if there's a PHPDoc comment on this node
                $comments = $parent->getAttribute('comments', []);
                foreach ($comments as $comment) {
                    if ($comment instanceof \PhpParser\Comment\Doc) {
                        // Check if our string position is within the PHPDoc range
                        $startLine = $comment->getStartLine();
                        $endLine = $comment->getEndLine();
                        $nodeLine = $node->getStartLine();

                        if ($nodeLine >= $startLine && $nodeLine <= $endLine) {
                            return true;
                        }
                    }
                }
            }

            $parent = $parent->getAttribute('parent');
        }

        return false;
    }
}