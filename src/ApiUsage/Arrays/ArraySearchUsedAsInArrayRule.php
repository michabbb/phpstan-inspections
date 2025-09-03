<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects array_search() usage that can be replaced with in_array().
 * Based on ArraySearchUsedAsInArrayInspector.java from PhpStorm EA Extended.
 * 
 * This rule handles binary comparison cases (=== false, !== false, === true).
 * Logical operand cases are handled by ArraySearchLogicalUsageRule.
 * 
 * @implements Rule<BinaryOp>
 */
final class ArraySearchUsedAsInArrayRule implements Rule
{
    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof BinaryOp\Identical && !$node instanceof BinaryOp\NotIdentical) {
            return [];
        }

        $arraySearchCall = $this->findArraySearchCall($node);
        if ($arraySearchCall === null) {
            return [];
        }

        $errors = [];
        $constantValue = $this->findBooleanConstant($node, $arraySearchCall);
        
        if ($constantValue !== null) {
            if ($constantValue === 'true') {
                $errors[] = RuleErrorBuilder::message(
                    'This makes no sense, as array_search(...) never returns true.'
                )
                ->identifier('arraySearch.compareWithTrue')
                ->line($node->getStartLine())
                ->build();
            } elseif ($constantValue === 'false') {
                $errors[] = RuleErrorBuilder::message(
                    "'in_array(...)' would fit more here (clarifies intention, improves maintainability)."
                )
                ->identifier('arraySearch.compareWithFalse')
                ->line($node->getStartLine())
                ->build();
            }
        }

        return $errors;
    }

    private function findArraySearchCall(BinaryOp $node): ?Node\Expr\FuncCall
    {
        if ($node->left instanceof Node\Expr\FuncCall && $this->isArraySearchCall($node->left)) {
            return $node->left;
        }

        if ($node->right instanceof Node\Expr\FuncCall && $this->isArraySearchCall($node->right)) {
            return $node->right;
        }

        return null;
    }

    private function isArraySearchCall(Node\Expr\FuncCall $funcCall): bool
    {
        return $funcCall->name instanceof Node\Name
            && (string) $funcCall->name === 'array_search'
            && count($funcCall->getArgs()) >= 2;
    }

    private function findBooleanConstant(BinaryOp $node, Node\Expr\FuncCall $arraySearchCall): ?string
    {
        $otherOperand = $node->left === $arraySearchCall ? $node->right : $node->left;
        
        if ($otherOperand instanceof Node\Expr\ConstFetch) {
            return (string) $otherOperand->name;
        }

        return null;
    }
}