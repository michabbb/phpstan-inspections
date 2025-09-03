<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of substr() or mb_substr() with negative length to check string endings
 * and suggests using str_ends_with() instead for better maintainability.
 *
 * This rule identifies patterns like:
 * - substr($str, -strlen($suffix)) === $suffix → str_ends_with($str, $suffix)
 * - mb_substr($str, -mb_strlen($suffix)) !== $suffix → !str_ends_with($str, $suffix)
 *
 * @implements Rule<BinaryOp>
 */
class StrEndsWithCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Only process equality comparison operations
        if (!$this->isEqualityComparison($node)) {
            return [];
        }

        // Find substr/mb_substr call in the binary operation
        $substrCall = $this->findSubstrCall($node);
        if ($substrCall === null) {
            return [];
        }

        // Extract the second argument of substr (should be -strlen(...) or -mb_strlen(...))
        $substrArgs = $substrCall->getArgs();
        if (count($substrArgs) !== 2) {
            return [];
        }

        $lengthArg = $substrArgs[1]->value;
        $expectedSuffix = $this->extractExpectedSuffix($lengthArg);
        if ($expectedSuffix === null) {
            return [];
        }

        // Check if the other side of the comparison matches the expected suffix
        $otherOperand = $this->getOtherOperand($node, $substrCall);
        if ($otherOperand === null) {
            return [];
        }

        // Check if the operands are equivalent
        if (!$this->areOperandsEquivalent($expectedSuffix, $otherOperand, $scope)) {
            return [];
        }

        // Determine the replacement suggestion
        $replacement = $this->buildReplacement($substrCall, $expectedSuffix, $node);

        return [
            RuleErrorBuilder::message(
                sprintf('Can be replaced by \'%s\' (improves maintainability).', $replacement)
            )
                ->identifier('string.substrEndsWith')
                ->line($substrCall->getStartLine())
                ->build(),
        ];
    }

    private function findSubstrCall(BinaryOp $binaryOp): ?FuncCall
    {
        // Check left side
        if ($binaryOp->left instanceof FuncCall && $this->isSubstrCall($binaryOp->left)) {
            return $binaryOp->left;
        }

        // Check right side
        if ($binaryOp->right instanceof FuncCall && $this->isSubstrCall($binaryOp->right)) {
            return $binaryOp->right;
        }

        return null;
    }

    private function isSubstrCall(FuncCall $node): bool
    {
        if ($node->name instanceof Name) {
            $functionName = $node->name->toString();
            return ($functionName === 'substr' || $functionName === 'mb_substr')
                && count($node->getArgs()) === 2;
        }

        return false;
    }

    private function isEqualityComparison(BinaryOp $binaryOp): bool
    {
        return $binaryOp instanceof Identical
            || $binaryOp instanceof NotIdentical
            || $binaryOp instanceof Equal
            || $binaryOp instanceof NotEqual;
    }

    private function extractExpectedSuffix(Node $lengthArg): ?Node
    {
        // Check if it's -strlen(...) or -mb_strlen(...)
        if ($lengthArg instanceof UnaryMinus) {
            $operand = $lengthArg->expr;
            if ($operand instanceof FuncCall && $operand->name instanceof Name) {
                $functionName = $operand->name->toString();
                if (($functionName === 'strlen' || $functionName === 'mb_strlen')
                    && count($operand->getArgs()) === 1) {
                    return $operand->getArgs()[0]->value;
                }
            }
        }

        return null;
    }

    private function getOtherOperand(BinaryOp $binaryOp, FuncCall $substrCall): ?Node
    {
        if ($binaryOp->left === $substrCall) {
            return $binaryOp->right;
        }

        if ($binaryOp->right === $substrCall) {
            return $binaryOp->left;
        }

        return null;
    }

    private function areOperandsEquivalent(Node $expected, Node $actual, Scope $scope): bool
    {
        // For now, we'll do a simple structural comparison
        // In a more sophisticated implementation, we could use PHPStan's type system
        // to check for equivalence, but this covers the basic cases

        if ($expected instanceof Node\Expr\Variable && $actual instanceof Node\Expr\Variable) {
            return $expected->name === $actual->name;
        }

        if ($expected instanceof Node\Scalar\String_ && $actual instanceof Node\Scalar\String_) {
            return $expected->value === $actual->value;
        }

        // For more complex expressions, we could extend this
        // For now, we'll be conservative and only match simple cases
        return false;
    }

    private function buildReplacement(FuncCall $substrCall, Node $suffix, BinaryOp $binaryOp): string
    {
        $stringArg = $substrCall->getArgs()[0]->value;
        $isNegated = $binaryOp instanceof NotIdentical || $binaryOp instanceof NotEqual;

        $strEndsWithCall = sprintf(
            'str_ends_with(%s, %s)',
            $this->nodeToString($stringArg),
            $this->nodeToString($suffix)
        );

        if ($isNegated) {
            return '!'.$strEndsWithCall;
        }

        return $strEndsWithCall;
    }

    private function nodeToString(Node $node): string
    {
        // Simple conversion for basic cases
        if ($node instanceof Node\Expr\Variable) {
            return '$'.$node->name;
        }

        if ($node instanceof Node\Scalar\String_) {
            return "'".$node->value."'";
        }

        // For more complex expressions, we could use a more sophisticated approach
        // For now, return a placeholder
        return '...';
    }
}