<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Unset_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests merging consecutive unset() statements for better code style.
 *
 * This rule detects when multiple consecutive unset() statements can be combined
 * into a single unset() call with multiple arguments. This improves code readability
 * and can be slightly more efficient.
 *
 * @implements Rule<Node\FunctionLike>
 */
class UnsetConstructsCanBeMergedRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return [];
        }

        if ($node instanceof ClassMethod && $node->isAbstract()) {
            return [];
        }

        if ($node->stmts === null) {
            return [];
        }

        $errors = [];
        $statements = $node->stmts;

        for ($i = 1; $i < count($statements); $i++) {
            $currentStmt = $statements[$i];
            $previousStmt = $statements[$i - 1];

            // Check if both current and previous statements are unset expressions
            if ($this->isUnsetExpression($currentStmt) && $this->isUnsetExpression($previousStmt)) {
                $errors[] = RuleErrorBuilder::message(
                    "Can be replaced with 'unset(..., ...)' construction (simplification)."
                )
                    ->identifier('unset.canBeMerged')
                    ->line($currentStmt->getStartLine())
                    ->tip('Consider merging consecutive unset() statements into a single call')
                    ->build();
            }
        }

        return $errors;
    }

    private function isUnsetExpression(Node $stmt): bool
    {
        // Handle direct Unset_ statements
        if ($stmt instanceof Unset_) {
            return true;
        }

        // Handle Expression statements that contain Unset_
        if ($stmt instanceof Expression && $stmt->expr instanceof Unset_) {
            return true;
        }

        return false;
    }
}