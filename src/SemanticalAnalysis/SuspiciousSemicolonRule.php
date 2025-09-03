<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Nop;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects suspicious semicolons used as body of control structures.
 *
 * This rule identifies semicolons that are used as the body of control structures
 * like if/elseif/else statements or loops. Such semicolons are treated as empty
 * statements and may indicate a bug where the intended code was accidentally
 * replaced with a semicolon.
 *
 * This rule detects:
 * - Semicolons used as the body of if statements
 * - Semicolons used as the body of elseif statements
 * - Semicolons used as the body of else statements
 * - Semicolons used as the body of loop constructs (for, foreach, while, do-while)
 *
 * @implements Rule<Stmt>
 */
class SuspiciousSemicolonRule implements Rule
{
    public function getNodeType(): string
    {
        return Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check if statements with empty body
        if ($node instanceof If_) {
            // For if (condition) ; patterns, the statement array might be empty or contain a Nop
            if (empty($node->stmts) || (count($node->stmts) === 1 && $node->stmts[0] instanceof Nop)) {
                $errors[] = RuleErrorBuilder::message("Probably a bug, because ';' treated as body.")
                    ->identifier('statement.suspiciousSemicolon')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        // Check elseif statements with empty body
        if ($node instanceof ElseIf_) {
            if (empty($node->stmts) || (count($node->stmts) === 1 && $node->stmts[0] instanceof Nop)) {
                $errors[] = RuleErrorBuilder::message("Probably a bug, because ';' treated as body.")
                    ->identifier('statement.suspiciousSemicolon')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        // Check else statements with empty body
        if ($node instanceof Else_) {
            if (empty($node->stmts) || (count($node->stmts) === 1 && $node->stmts[0] instanceof Nop)) {
                $errors[] = RuleErrorBuilder::message("Probably a bug, because ';' treated as body.")
                    ->identifier('statement.suspiciousSemicolon')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        // Check loop constructs with empty body
        if ($node instanceof For_) {
            if (empty($node->stmts) || (count($node->stmts) === 1 && $node->stmts[0] instanceof Nop)) {
                $errors[] = RuleErrorBuilder::message("Probably a bug, because ';' treated as body.")
                    ->identifier('statement.suspiciousSemicolon')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        if ($node instanceof Foreach_) {
            if (empty($node->stmts) || (count($node->stmts) === 1 && $node->stmts[0] instanceof Nop)) {
                $errors[] = RuleErrorBuilder::message("Probably a bug, because ';' treated as body.")
                    ->identifier('statement.suspiciousSemicolon')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        if ($node instanceof While_) {
            if (empty($node->stmts) || (count($node->stmts) === 1 && $node->stmts[0] instanceof Nop)) {
                $errors[] = RuleErrorBuilder::message("Probably a bug, because ';' treated as body.")
                    ->identifier('statement.suspiciousSemicolon')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        if ($node instanceof Do_) {
            if (empty($node->stmts) || (count($node->stmts) === 1 && $node->stmts[0] instanceof Nop)) {
                $errors[] = RuleErrorBuilder::message("Probably a bug, because ';' treated as body.")
                    ->identifier('statement.suspiciousSemicolon')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}