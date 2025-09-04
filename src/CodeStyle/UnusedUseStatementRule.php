<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unused use statements using simple string-based search.
 * 
 * This rule identifies use statements that import classes but are never 
 * actually used in the file by performing a simple string search.
 * 
 * @implements Rule<Use_>
 */
final class UnusedUseStatementRule implements Rule
{
    public function getNodeType(): string
    {
        return Use_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Only handle normal class/interface/trait imports, not functions or constants
        if ($node->type !== Use_::TYPE_NORMAL) {
            return [];
        }

        $errors = [];
        $filePath = $scope->getFile();
        
        // Read the file content
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return [];
        }
        
        foreach ($node->uses as $use) {
            $fullName = $use->name->toString();
            $shortName = $use->alias?->toString() ?? $use->name->getLast();
            
            // Remove the use statement lines from content to avoid false positives
            $lines = explode("\n", $fileContent);
            $startLine = $use->getStartLine() - 1; // Convert to 0-based
            $endLine = $use->getEndLine() - 1;
            
            $searchContent = '';
            foreach ($lines as $lineNum => $line) {
                if ($lineNum < $startLine || $lineNum > $endLine) {
                    $searchContent .= $line . "\n";
                }
            }
            
            // Check if the short name is used anywhere in the remaining content
            // Using word boundaries to avoid false positives with partial matches
            $pattern = '/\b' . preg_quote($shortName, '/') . '\b/';
            
            if (preg_match($pattern, $searchContent) === 0) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf('Unused import "%s".', $fullName)
                )
                ->identifier('use.unused')
                ->line($use->getStartLine())
                ->tip('Remove this unused import to clean up your code.')
                ->build();
            }
        }

        return $errors;
    }
}