<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Expr\ConstFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PhpParser\PrettyPrinter\Standard; // Import the pretty printer

/**
 * Detects when strstr() or stristr() are used where strpos() or stripos() would be more appropriate.
 *
 * This rule identifies cases where strstr() is used in boolean contexts (comparisons with false,
 * logical operations, conditions) where strpos() would be more memory-efficient since it only
 * returns the position instead of the substring.
 *
 * @implements Rule<Node>
 */
final class StrStrUsedAsStrPosRule implements Rule
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

        // Check if it's a comparison with false and contains strstr/stristr call
        $funcCall = $this->findStrStrCall($node, $scope);
        if ($funcCall === null) {
            return [];
        }

        $functionName = $this->resolveFunctionName($funcCall);
        if ($functionName === null) {
            return [];
        }

        $mapping = [
            'strstr' => 'strpos',
            'stristr' => 'stripos',
        ];

        if (!isset($mapping[$functionName])) {
            return [];
        }

        $args = $funcCall->getArgs();
        if (count($args) < 2) {
            return [];
        }

        $haystack = $args[0]->value;
        $needle = $args[1]->value;

        $replacementFunction = $mapping[$functionName];
        $suggestedCall = sprintf(
            '%s(%s, %s)',
            $replacementFunction,
            $this->prettyPrinter->prettyPrintExpr($haystack),
            $this->prettyPrinter->prettyPrintExpr($needle)
        );

        $operator = $this->getOperatorString($node);
        $suggestedReplacement = sprintf('%s %s false', $suggestedCall, $operator);

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' should be used instead (saves memory).", $suggestedReplacement)
            )
                ->identifier('string.strstrUsedAsStrpos')
                ->line($funcCall->getStartLine())
                ->build(),
        ];
    }

    private function findStrStrCall(BinaryOp $binaryOp, Scope $scope): ?FuncCall
    {
        // Check if this is a comparison with false/boolean
        if (!$this->isComparisonWithFalse($binaryOp, $scope)) {
            return null;
        }

        // Check left side
        if ($binaryOp->left instanceof FuncCall) {
            $funcName = $this->resolveFunctionName($binaryOp->left);
            if ($funcName && in_array($funcName, ['strstr', 'stristr'], true)) {
                return $binaryOp->left;
            }
        }

        // Check right side
        if ($binaryOp->right instanceof FuncCall) {
            $funcName = $this->resolveFunctionName($binaryOp->right);
            if (in_array($funcName, ['strstr', 'stristr'], true)) {
                return $binaryOp->right;
            }
        }

        return null;
    }

    private function isComparisonWithFalse(BinaryOp $binaryOp, Scope $scope): bool
    {
        // Check AST nodes directly for false literal
        if ($binaryOp->left instanceof ConstFetch && $binaryOp->left->name->toString() === 'false') {
            return true;
        }
        if ($binaryOp->right instanceof ConstFetch && $binaryOp->right->name->toString() === 'false') {
            return true;
        }

        // Also check types as fallback
        $leftType = $scope->getType($binaryOp->left);
        $rightType = $scope->getType($binaryOp->right);

        // Check if either operand is false
        return ($leftType instanceof ConstantBooleanType && $leftType->getValue() === false) ||
               ($rightType instanceof ConstantBooleanType && $rightType->getValue() === false);
    }

    private function resolveFunctionName(FuncCall $funcCall): ?string
    {
        if (!$funcCall->name instanceof Node\Name) {
            return null;
        }

        return strtolower((string) $funcCall->name);
    }

    private function getOperatorString(BinaryOp $binaryOp): string
    {
        if ($binaryOp instanceof Identical) {
            return '===';
        }
        if ($binaryOp instanceof NotIdentical) {
            return '!==';
        }
        if ($binaryOp instanceof Equal) {
            return '==';
        }
        if ($binaryOp instanceof NotEqual) {
            return '!=';
        }
        return ''; // Should not happen for equality ops
    }
}
