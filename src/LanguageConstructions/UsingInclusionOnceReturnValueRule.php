<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Warns about using return values from include_once/require_once statements.
 *
 * This rule detects when the return value of include_once or require_once is being used.
 * These functions only return the expected result on the first call; subsequent calls
 * return true, which can lead to unexpected behavior. Suggests using include/require
 * or refactoring to use functions/methods instead.
 *
 * @implements Rule<Node>
 */
final class UsingInclusionOnceReturnValueRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Check assignments with include_once/require_once on the right side
        if ($node instanceof Assign && $node->expr instanceof Include_) {
            return $this->checkIncludeOnceUsage($node->expr);
        }

        // Check return statements with include_once/require_once
        if ($node instanceof Return_ && $node->expr instanceof Include_) {
            return $this->checkIncludeOnceUsage($node->expr);
        }

        // Check if include_once/require_once is used in conditions
        if ($node instanceof Node\Stmt\If_ && $node->cond instanceof Include_) {
            return $this->checkIncludeOnceUsage($node->cond);
        }

        // Check other expression contexts where include_once/require_once might be used
        if ($node instanceof Node\Expr\BinaryOp) {
            $errors = [];
            if ($node->left instanceof Include_) {
                $errors = array_merge($errors, $this->checkIncludeOnceUsage($node->left));
            }
            if ($node->right instanceof Include_) {
                $errors = array_merge($errors, $this->checkIncludeOnceUsage($node->right));
            }
            return $errors;
        }

        return [];
    }

    private function checkIncludeOnceUsage(Include_ $node): array
    {
        // Only interested in include_once or require_once
        if ($node->type !== Include_::TYPE_INCLUDE_ONCE && $node->type !== Include_::TYPE_REQUIRE_ONCE) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Only the first call returns the proper/expected result. Subsequent calls will return "true". ' .
                'To be on the safe side it is worth using include/require or refactor your code using functions/methods.'
            )
                ->identifier('inclusion.onceReturnValue')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}