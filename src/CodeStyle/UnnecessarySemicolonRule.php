<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\Declare_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unnecessary semicolons in PHP code.
 *
 * This rule identifies empty statements (standalone semicolons) that create 
 * no-operation statements. These are typically found as bare semicolons on 
 * their own lines and can be safely removed.
 *
 * Based on the Java original UnnecessarySemicolonInspector.java from EA Inspections.
 *
 * @implements Rule<Stmt>
 */
class UnnecessarySemicolonRule implements Rule
{
    public function getNodeType(): string
    {
        return Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Skip Blade files (from Java original)
        if (str_ends_with($scope->getFile(), '.blade.php')) {
            return [];
        }

        // Check for empty statements (following Java logic: statements with no children)
        if ($this->isEmptyStatement($node, $scope)) {
            $errors[] = RuleErrorBuilder::message('Unnecessary semicolon.')
                ->identifier('statement.unnecessarySemicolon')
                ->line($node->getStartLine())
                ->build();
        }

        // Handle echo statements at end of file (from Java original)
        if ($this->isUnnecessaryEchoSemicolon($node, $scope)) {
            $errors[] = RuleErrorBuilder::message('Unnecessary semicolon.')
                ->identifier('statement.unnecessarySemicolon')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Check if a statement is empty (has no children) following Java original logic.
     * Only flag Nop nodes (empty statements) that are not in control structures.
     */
    private function isEmptyStatement(Node $node, Scope $scope): bool
    {
        // Only check Nop nodes (empty statements like bare semicolons)
        if (!$node instanceof Nop) {
            return false;
        }

        // Get parent to check context (from Java original logic)
        $parent = $node->getAttribute('parent');
        
        // Skip if parent is control structure (from Java original)
        if ($parent instanceof If_ || 
            $parent instanceof ElseIf_ || 
            $parent instanceof Else_ ||
            $parent instanceof For_ ||
            $parent instanceof Foreach_ ||
            $parent instanceof While_ ||
            $parent instanceof Do_ ||
            $parent instanceof Switch_ ||
            $parent instanceof Declare_) {
            return false; // Don't flag - legitimate empty statement in control structure
        }

        // Additional validation: Check actual line content to avoid false positives
        // Only flag lines that are truly just semicolons
        if ($this->isActuallyEmptyStatement($node, $scope)) {
            return true;
        }

        return false; // Don't flag if line contains actual code
    }

    /**
     * Verify that the line actually contains only a semicolon (empty statement).
     * This prevents false positives on legitimate statements.
     */
    private function isActuallyEmptyStatement(Node $node, Scope $scope): bool
    {
        $fileContents = file_get_contents($scope->getFile());
        if ($fileContents === false) {
            return false; // Conservative: if we can't read, don't flag
        }

        $lines = explode("\n", $fileContents);
        $lineNumber = $node->getStartLine();
        
        if ($lineNumber <= 0 || $lineNumber > count($lines)) {
            return false;
        }

        $lineContent = trim($lines[$lineNumber - 1]);
        
        // Only flag if the line is truly just a semicolon
        return $lineContent === ';';
    }

    /**
     * Handle echo statements at end of file (from Java original).
     * Echo statements without following PHP elements should have semicolon flagged.
     */
    private function isUnnecessaryEchoSemicolon(Node $node, Scope $scope): bool
    {
        if (!$node instanceof Echo_) {
            return false;
        }

        // This is a simplified implementation - the Java original has more complex logic
        // for checking if echo is at end of file with no following PHP elements.
        // For now, we'll be conservative and not flag echo statements to avoid false positives.
        return false;
    }
}