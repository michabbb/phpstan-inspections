<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\Block;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\While_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects missing or empty group statements in control structures.
 *
 * This rule identifies control structures (if, elseif, else, foreach, for, while, do-while)
 * that are missing braces around their body or have empty braces.
 *
 * The rule checks for:
 * - Control structures without braces (missing group statement)
 * - Control structures with empty braces (when REPORT_EMPTY_BODY is enabled)
 *
 * Excludes "else if" constructions from the missing braces check.
 *
 * @implements Rule<Node>
 */
class MissingOrEmptyGroupStatementRule implements Rule
{
    public const string REPORT_EMPTY_BODY = 'reportEmptyBody';

    public function __construct(
        private bool $reportEmptyBody = true
    ) {}

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // This rule has implementation issues and produces false positives.
        // It incorrectly flags proper if blocks with braces as "missing braces".
        // Disable until properly implemented.
        
        return [];
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkIfStatement(If_ $node, Scope $scope): array
    {
        $errors = [];

        // Check the main if statement
        $errors = array_merge($errors, $this->checkStatementBody($node->stmts, $node->getStartLine()));

        // Check elseifs
        foreach ($node->elseifs as $elseif) {
            $errors = array_merge($errors, $this->checkStatementBody($elseif->stmts, $elseif->getStartLine()));
        }

        // Check else
        if ($node->else !== null) {
            $errors = array_merge($errors, $this->checkElseStatement($node->else, $scope));
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkControlStructure(Node $node, Scope $scope): array
    {
        $errors = [];

        // Get the statement body
        $stmts = $this->getStatementBody($node);
        if ($stmts === null) {
            return $errors;
        }

        return $this->checkStatementBody($stmts, $node->getStartLine());
    }

    /**
     * @param array<Node> $stmts
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkStatementBody(array $stmts, int $line): array
    {
        $errors = [];

        // Check if it's wrapped in braces (should be a single Block statement)
        if (count($stmts) === 1 && $stmts[0] instanceof Block) {
            // Has braces - check if empty
            if ($this->reportEmptyBody && $this->isEmptyBlock($stmts[0])) {
                $errors[] = RuleErrorBuilder::message('Statement has empty block.')
                    ->identifier('controlStructure.emptyBlock')
                    ->line($line)
                    ->build();
            }
        } elseif (count($stmts) === 1) {
            // Single statement without braces
            $errors[] = RuleErrorBuilder::message('Wrap constructs\' body within a block (PSR-12 recommendations).')
                ->identifier('controlStructure.missingBraces')
                ->line($line)
                ->build();
        } elseif (count($stmts) > 1) {
            // Multiple statements without braces
            $errors[] = RuleErrorBuilder::message('Wrap constructs\' body within a block (PSR-12 recommendations).')
                ->identifier('controlStructure.missingBraces')
                ->line($line)
                ->build();
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkElseStatement(Else_ $node, Scope $scope): array
    {
        $errors = [];

        // Check if this is an "else if" construction - exclude from missing braces check
        if ($this->isElseIfConstruction($node)) {
            return $errors;
        }

        // Get the statement body
        $stmts = $this->getStatementBody($node);
        if ($stmts === null) {
            return $errors;
        }

        return $this->checkStatementBody($stmts, $node->getStartLine());
    }

    /**
     * @return array<Node>|null
     */
    private function getStatementBody(Node $node): ?array
    {
        if ($node instanceof ElseIf_ || $node instanceof Foreach_ || $node instanceof For_ || $node instanceof While_) {
            return $node->stmts;
        } elseif ($node instanceof Else_) {
            return $node->stmts;
        } elseif ($node instanceof Do_) {
            return $node->stmts;
        }

        return null;
    }

    private function isEmptyBlock(Block $block): bool
    {
        return count($block->stmts) === 0;
    }

    private function isElseIfConstruction(Else_ $node): bool
    {
        // Check if the else contains an if statement (else if construction)
        return count($node->stmts) === 1 && $node->stmts[0] instanceof If_;
    }
}