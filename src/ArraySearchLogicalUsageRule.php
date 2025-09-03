<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects array_search() used as logical operand that can be replaced with in_array().
 * Based on ArraySearchUsedAsInArrayInspector.java from PhpStorm EA Extended.
 * 
 * This rule handles logical operand cases (if, while, ternary, &&, ||, !).
 * Binary comparison cases are handled by ArraySearchUsedAsInArrayRule.
 * 
 * @implements Rule<Node>
 */
final class ArraySearchLogicalUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $arraySearchCall = null;

        // Check for array_search in logical contexts
        if ($node instanceof Node\Stmt\If_) {
            $arraySearchCall = $this->extractArraySearchFromExpression($node->cond);
        } elseif ($node instanceof Node\Stmt\ElseIf_) {
            $arraySearchCall = $this->extractArraySearchFromExpression($node->cond);
        } elseif ($node instanceof Node\Stmt\While_) {
            $arraySearchCall = $this->extractArraySearchFromExpression($node->cond);
        } elseif ($node instanceof Node\Expr\Ternary) {
            $arraySearchCall = $this->extractArraySearchFromExpression($node->cond);
        } elseif ($node instanceof Node\Expr\BinaryOp\BooleanAnd) {
            // Only check direct array_search calls, not nested ones (to avoid duplicates)
            if ($node->left instanceof Node\Expr\FuncCall && $this->isArraySearchCall($node->left)) {
                $arraySearchCall = $node->left;
            } elseif ($node->right instanceof Node\Expr\FuncCall && $this->isArraySearchCall($node->right)) {
                $arraySearchCall = $node->right;
            }
        } elseif ($node instanceof Node\Expr\BinaryOp\BooleanOr) {
            // Only check direct array_search calls, not nested ones (to avoid duplicates)
            if ($node->left instanceof Node\Expr\FuncCall && $this->isArraySearchCall($node->left)) {
                $arraySearchCall = $node->left;
            } elseif ($node->right instanceof Node\Expr\FuncCall && $this->isArraySearchCall($node->right)) {
                $arraySearchCall = $node->right;
            }
        } elseif ($node instanceof Node\Expr\BooleanNot) {
            $arraySearchCall = $this->extractArraySearchFromExpression($node->expr);
        }

        if ($arraySearchCall === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "'in_array(...)' would fit more here (clarifies intention, improves maintainability)."
            )
            ->identifier('arraySearch.logicalOperand')
            ->line($arraySearchCall->getStartLine())
            ->build(),
        ];
    }

    private function extractArraySearchFromExpression(Node $expr): ?Node\Expr\FuncCall
    {
        if ($expr instanceof Node\Expr\FuncCall && $this->isArraySearchCall($expr)) {
            return $expr;
        }

        // Handle nested expressions
        if ($expr instanceof Node\Expr\BinaryOp\BooleanAnd || $expr instanceof Node\Expr\BinaryOp\BooleanOr) {
            $leftResult = $this->extractArraySearchFromExpression($expr->left);
            if ($leftResult !== null) {
                return $leftResult;
            }
            return $this->extractArraySearchFromExpression($expr->right);
        }

        if ($expr instanceof Node\Expr\BooleanNot) {
            return $this->extractArraySearchFromExpression($expr->expr);
        }

        return null;
    }

    private function isArraySearchCall(Node\Expr\FuncCall $funcCall): bool
    {
        return $funcCall->name instanceof Node\Name
            && (string) $funcCall->name === 'array_search'
            && count($funcCall->getArgs()) >= 2;
    }
}