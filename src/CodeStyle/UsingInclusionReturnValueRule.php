<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of return values from include/include_once and require/require_once statements.
 *
 * This rule identifies when the return value of include, include_once, require, or require_once
 * is being used in expressions, assignments, or function calls. Using the return mechanism
 * of inclusion statements is considered bad practice and should be avoided.
 *
 * This rule detects:
 * - Assignments using include/require return values
 * - Function calls using include/require return values as arguments
 * - Any other expression usage of include/require return values
 *
 * Recommended alternatives:
 * - Use include/require as standalone statements
 * - Refactor code to use functions/methods instead of relying on include return values
 * - Consider using autoloading and proper OOP patterns
 *
 * @implements Rule<Node>
 */
final class UsingInclusionReturnValueRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Check assignments with include/require on the right side
        if ($node instanceof Assign && $node->expr instanceof Include_) {
            return $this->checkIncludeUsage($node->expr);
        }

        // Check return statements with include/require
        if ($node instanceof Return_ && $node->expr instanceof Include_) {
            return $this->checkIncludeUsage($node->expr);
        }

        // Check if include/require is used in conditions
        if ($node instanceof Node\Stmt\If_ && $node->cond instanceof Include_) {
            return $this->checkIncludeUsage($node->cond);
        }

        // Check other expression contexts where include/require might be used
        if ($node instanceof Node\Expr\BinaryOp) {
            $errors = [];
            if ($node->left instanceof Include_) {
                $errors = array_merge($errors, $this->checkIncludeUsage($node->left));
            }
            if ($node->right instanceof Include_) {
                $errors = array_merge($errors, $this->checkIncludeUsage($node->right));
            }
            return $errors;
        }

        return [];
    }

    private function checkIncludeUsage(Include_ $node): array
    {
        // This rule applies to all include types (include, include_once, require, require_once)
        return [
            RuleErrorBuilder::message(
                'Operating on this return mechanism is considered a bad practice. OOP can be used instead.'
            )
                ->identifier('inclusion.returnValueUsage')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}