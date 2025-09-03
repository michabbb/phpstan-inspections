<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Ifs;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\GroupStatement;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects nested positive if statements that can be merged with their parent.
 *
 * This rule identifies if constructs that are nested inside another if statement
 * and can be safely merged by combining their conditions. This eliminates unnecessary
 * nesting and improves code readability.
 *
 * The rule detects patterns like:
 * - Nested if statements where the inner if is the only statement in the parent's body
 * - Conditions that don't contain OR operators (to avoid complex boolean logic)
 * - Equivalent else branches when present
 *
 * @implements Rule<If_>
 */
class NestedPositiveIfStatementsRule implements Rule
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

        $errors = [];

        // Check if this if statement contains nested if statements that can be merged
        $errors = array_merge($errors, $this->checkForMergeableNestedIfs($node));

        return $errors;
    }

    /**
     * Check if this if statement contains nested if statements that can be merged
     * 
     * @return list<RuleError>
     */
    private function checkForMergeableNestedIfs(If_ $node): array
    {
        $errors = [];
        
        // Check the main if branch
        if (count($node->stmts) === 1 && $node->stmts[0] instanceof If_) {
            $nestedIf = $node->stmts[0];
            if ($this->canMergeWithParentIf($nestedIf, $node)) {
                $errors[] = RuleErrorBuilder::message('If construct can be merged with parent one.')
                    ->identifier('if.nestedPositive')
                    ->line($nestedIf->getStartLine())
                    ->build();
            }
        }
        
        // Check the else branch if it exists
        if ($node->else !== null && count($node->else->stmts) === 1 && $node->else->stmts[0] instanceof If_) {
            $nestedIf = $node->else->stmts[0];
            if ($this->canMergeWithParentElse($nestedIf, $node->else)) {
                $errors[] = RuleErrorBuilder::message('If construct can be merged with parent one.')
                    ->identifier('if.nestedPositive')
                    ->line($nestedIf->getStartLine())
                    ->build();
            }
        }
        
        return $errors;
    }

    /**
     * Check if the nested if can be merged with its parent if statement
     */
    private function canMergeWithParentIf(If_ $nestedIf, If_ $parentIf): bool
    {
        // Both must not have elseif branches
        if (!empty($nestedIf->elseifs) || !empty($parentIf->elseifs)) {
            return false;
        }

        // Conditions must not contain OR operators
        if ($this->containsOrOperator($nestedIf->cond) || $this->containsOrOperator($parentIf->cond)) {
            return false;
        }

        // Check else branches compatibility
        $nestedElse = $nestedIf->else;
        $parentElse = $parentIf->else;

        if ($nestedElse === null) {
            // If nested has no else, parent must also have no else
            return $parentElse === null;
        }

        if ($parentElse === null) {
            // If nested has else but parent doesn't, cannot merge
            return false;
        }

        // Both have else branches - they must be equivalent
        return $this->areElseBranchesEquivalent($nestedElse, $parentElse);
    }

    /**
     * Check if the nested if can be merged with its parent else statement
     */
    private function canMergeWithParentElse(If_ $nestedIf, Else_ $parentElse): bool
    {
        // Nested if must not have elseif branches
        if (!empty($nestedIf->elseifs)) {
            return false;
        }

        // Condition must not contain OR operators
        if ($this->containsOrOperator($nestedIf->cond)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a condition expression contains OR operators
     */
    private function containsOrOperator(Node $condition): bool
    {
        $finder = new NodeFinder();
        $orNodes = $finder->find($condition, static fn(Node $node): bool =>
            $node instanceof BooleanOr || $node instanceof LogicalOr
        );

        return !empty($orNodes);
    }

    /**
     * Check if two else branches are structurally equivalent
     */
    private function areElseBranchesEquivalent(Else_ $else1, Else_ $else2): bool
    {
        $stmt1 = $else1->stmts;
        $stmt2 = $else2->stmts;

        if (count($stmt1) !== count($stmt2)) {
            return false;
        }

        // For simplicity, we'll do a basic structural comparison
        // In a more sophisticated implementation, we could compare the AST more deeply
        for ($i = 0; $i < count($stmt1); $i++) {
            if (get_class($stmt1[$i]) !== get_class($stmt2[$i])) {
                return false;
            }
        }

        return true;
    }
}