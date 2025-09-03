<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects redundant else clauses in if statements.
 *
 * This rule identifies if-else constructs where the else clause is unnecessary
 * because the if body ends with a return, break, continue, throw, or exit statement.
 * In such cases, the else clause can be safely removed without changing the semantic,
 * leading to clearer intention and lower complexity numbers.
 *
 * @implements Rule<If_>
 */
class RedundantElseClauseRule implements Rule
{
    public function getNodeType(): string
    {
        return If_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof If_) {
            return [];
        }

        // Skip if-else-if constructs (else branch contains another if)
        if ($this->isPartOfElseIfChain($node)) {
            return [];
        }

        // Check if if body is wrapped in braces
        if (!$this->hasBracedBody($node)) {
            return [];
        }

        // Check if there are alternative branches
        if (!$this->hasAlternativeBranches($node)) {
            return [];
        }

        // Find the last statement in if body
        $lastStatement = $this->getLastStatement($node->stmts);
        if ($lastStatement === null) {
            return [];
        }

        // Check if last statement is a return point
        if (!$this->isReturnPoint($lastStatement)) {
            return [];
        }

        // Report on else/elseif branches
        return $this->reportRedundantBranches($node);
    }

    private function isPartOfElseIfChain(If_ $node): bool
    {
        $parent = $node->getAttribute('parent');
        return $parent instanceof Else_;
    }

    private function hasBracedBody(If_ $node): bool
    {
        return $node->stmts !== null && is_array($node->stmts);
    }

    private function hasAlternativeBranches(If_ $node): bool
    {
        return !empty($node->elseifs) || $node->else !== null;
    }

    private function getLastStatement(array $statements): ?Node
    {
        if (empty($statements)) {
            return null;
        }

        $lastStmt = end($statements);
        if ($lastStmt instanceof Expression) {
            return $lastStmt->expr;
        }

        return $lastStmt;
    }

    private function isReturnPoint(Node $node): bool
    {
        return $node instanceof Return_
            || $node instanceof Break_
            || $node instanceof Continue_
            || $node instanceof Throw_
            || $node instanceof Exit_;
    }

    private function reportRedundantBranches(If_ $node): array
    {
        $errors = [];

        // Report elseif branches
        foreach ($node->elseifs as $elseif) {
            $errors[] = RuleErrorBuilder::message(
                'Child instructions can be extracted here (clearer intention, lower complexity numbers).'
            )
                ->identifier('controlFlow.redundantElse')
                ->line($elseif->getStartLine())
                ->build();
        }

        // Report else branch
        if ($node->else !== null) {
            $errors[] = RuleErrorBuilder::message(
                'Child instructions can be extracted here (clearer intention, lower complexity numbers).'
            )
                ->identifier('controlFlow.redundantElse')
                ->line($node->else->getStartLine())
                ->build();
        }

        return $errors;
    }
}