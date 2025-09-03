<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use PhpParser\PrettyPrinter\Standard;

/**
 * Detects when strpos() or mb_strpos() can be replaced with str_contains().
 *
 * This rule identifies cases where strpos() or mb_strpos() is used in comparisons
 * with false (=== false or !== false) and suggests using str_contains() instead,
 * which is more readable and semantically clearer for substring detection.
 *
 * @implements Rule<Node>
 */
final class StrContainsCanBeUsedRule implements Rule
{
    private Standard $prettyPrinter;

    public function __construct()
    {
        $this->prettyPrinter = new Standard();
    }

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Only process binary operations
        if (!$node instanceof BinaryOp) {
            return [];
        }

        // Check if it's a comparison with false
        if (!$this->isComparisonWithFalse($node, $scope)) {
            return [];
        }

        // Find strpos/mb_strpos calls in the binary operation
        $funcCall = $this->findStrPosCall($node, $scope);
        if ($funcCall === null) {
            return [];
        }

        $functionName = $this->getFunctionName($funcCall, $scope);
        if ($functionName === null || !in_array($functionName, ['strpos', 'mb_strpos'], true)) {
            return [];
        }

        $args = $funcCall->getArgs();
        if (count($args) !== 2) {
            return [];
        }

        $haystack = $args[0]->value;
        $needle = $args[1]->value;

        $replacement = $this->buildReplacement($node, $haystack, $needle);

        return [
            RuleErrorBuilder::message(
                sprintf("Can be replaced by '%s' (improves maintainability).", $replacement)
            )
                ->identifier('string.strContainsCanBeUsed')
                ->line($funcCall->getStartLine())
                ->build(),
        ];
    }

    private function getFunctionName(FuncCall $funcCall, Scope $scope): ?string
    {
        if (!$funcCall->name instanceof Node\Name) {
            return null;
        }

        $resolvedName = $scope->resolveName($funcCall->name);
        if ($resolvedName === '') {
            return null;
        }

        return strtolower($resolvedName);
    }

    private function isComparisonWithFalse(BinaryOp $binaryOp, Scope $scope): bool
    {
        // Must be identical (===) or not identical (!==)
        if (!$binaryOp instanceof Identical && !$binaryOp instanceof NotIdentical) {
            return false;
        }

        // Check if one operand is false
        $leftType = $scope->getType($binaryOp->left);
        $rightType = $scope->getType($binaryOp->right);

        $hasFalseLeft = $leftType->isFalse()->yes();
        $hasFalseRight = $rightType->isFalse()->yes();

        return $hasFalseLeft || $hasFalseRight;
    }

    private function findStrPosCall(BinaryOp $binaryOp, Scope $scope): ?FuncCall
    {
        // Check left operand
        if ($binaryOp->left instanceof FuncCall) {
            $functionName = $this->getFunctionName($binaryOp->left, $scope);
            if ($functionName !== null && in_array($functionName, ['strpos', 'mb_strpos'], true)) {
                return $binaryOp->left;
            }
        }

        // Check right operand
        if ($binaryOp->right instanceof FuncCall) {
            $functionName = $this->getFunctionName($binaryOp->right, $scope);
            if ($functionName !== null && in_array($functionName, ['strpos', 'mb_strpos'], true)) {
                return $binaryOp->right;
            }
        }

        return null;
    }

    private function buildReplacement(BinaryOp $binaryOp, Node $haystack, Node $needle): string
    {
        $haystackStr = $haystack instanceof Node\Expr ? $this->prettyPrinter->prettyPrintExpr($haystack) : '';
        $needleStr = $needle instanceof Node\Expr ? $this->prettyPrinter->prettyPrintExpr($needle) : '';

        $strContainsCall = sprintf('str_contains(%s, %s)', $haystackStr, $needleStr);

        // If it's === false, we need !str_contains
        // If it's !== false, we use str_contains directly
        if ($binaryOp instanceof Identical) {
            return '!' . $strContainsCall;
        }

        return $strContainsCall;
    }
}