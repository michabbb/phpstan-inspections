<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\UnaryOp\BooleanNot;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects 'isset(...)' constructs that can be merged.
 *
 * This rule identifies:
 * - Multiple 'isset(...)' calls connected with '&&' that can be merged into a single 'isset(..., ...)'
 * - Multiple '!isset(...)' calls connected with '||' that can be merged into a single '!isset(..., ...)'
 *
 * @implements Rule<BinaryOp>
 */
class IssetConstructsCanBeMergedRule implements Rule
{
    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof BinaryOp) {
            return [];
        }

        $operator = null;
        if ($node instanceof BooleanAnd) {
            $operator = '&&';
        } elseif ($node instanceof BooleanOr) {
            $operator = '||';
        } else {
            return [];
        }

        $fragments = $this->extractFragments($node, $operator);
        if (count($fragments) < 2) {
            return [];
        }

        $issetFragments = [];
        $nonIssetFragments = [];

        foreach ($fragments as $fragment) {
            if ($fragment instanceof Isset_) {
                $issetFragments[] = $fragment;
            } elseif ($fragment instanceof BooleanNot && $fragment->expr instanceof Isset_) {
                $nonIssetFragments[] = $fragment->expr;
            }
        }

        $errors = [];

        // Check for isset && isset pattern
        if ($operator === '&&' && count($issetFragments) > 1) {
            $errors[] = RuleErrorBuilder::message(
                "This 'isset(..., ...[, ...])' can be merged into the previous 'isset(..., ...[, ...])'."
            )
                ->identifier('isset.constructsMerge')
                ->line($node->getStartLine())
                ->build();
        }

        // Check for !isset || !isset pattern
        if ($operator === '||' && count($nonIssetFragments) > 1) {
            $errors[] = RuleErrorBuilder::message(
                "This '!isset(..., ...[, ...])' can be merged into the previous '!isset(..., ...[, ...])'."
            )
                ->identifier('isset.constructsMerge')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Extract all fragments from a binary expression with the same operator.
     *
     * @param BinaryOp $binary
     * @param string $operator
     * @return Node[]
     */
    private function extractFragments(BinaryOp $binary, string $operator): array
    {
        $result = [];

        if ($this->getOperator($binary) === $operator) {
            $leftFragments = $binary->left instanceof BinaryOp
                ? $this->extractFragments($binary->left, $operator)
                : [$binary->left];

            $rightFragments = $binary->right instanceof BinaryOp
                ? $this->extractFragments($binary->right, $operator)
                : [$binary->right];

            $result = array_merge($leftFragments, $rightFragments);
        } else {
            $result[] = $binary;
        }

        return $result;
    }

    /**
     * Get the operator string for a binary operation.
     */
    private function getOperator(BinaryOp $binary): ?string
    {
        if ($binary instanceof BooleanAnd) {
            return '&&';
        }
        if ($binary instanceof BooleanOr) {
            return '||';
        }
        return null;
    }
}