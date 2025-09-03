<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects when strpos() or mb_strpos() can be replaced with str_starts_with().
 *
 * This rule identifies cases where strpos() or mb_strpos() is used in comparisons
 * with 0 (=== 0 or !== 0) and suggests using str_starts_with() instead, which
 * improves code readability and maintainability.
 *
 * Examples of violations:
 * - strpos($haystack, $needle) === 0 → str_starts_with($haystack, $needle)
 * - strpos($haystack, $needle) !== 0 → !str_starts_with($haystack, $needle)
 * - mb_strpos($haystack, $needle) === 0 → str_starts_with($haystack, $needle)
 *
 * @implements Rule<FuncCall>
 */
final class StrStartsWithCanBeUsedRule implements Rule
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

        // Check if this is a comparison with 0
        if (!$this->isComparisonWithZero($node)) {
            return [];
        }

        // Find strpos/mb_strpos function call in the binary operation
        $funcCall = $this->findStrposCall($node);
        if ($funcCall === null) {
            return [];
        }

        $functionName = $this->getFunctionName($funcCall);
        if ($functionName === null || !in_array($functionName, ['strpos', 'mb_strpos'], true)) {
            return [];
        }

        $args = $funcCall->getArgs();
        if (count($args) !== 2) {
            return [];
        }

        // Build the suggested replacement
        $haystack = $args[0]->value;
        $needle = $args[1]->value;

        $replacement = sprintf(
            'str_starts_with(%s, %s)',
            $this->getPrettyPrint($haystack),
            $this->getPrettyPrint($needle)
        );

        // Determine if we need negation
        $needsNegation = $node instanceof NotIdentical || $node instanceof NotEqual;

        if ($needsNegation) {
            $replacement = '!' . $replacement;
        }

        return [
            RuleErrorBuilder::message(
                sprintf("Can be replaced by '%s' (improves maintainability).", $replacement)
            )
                ->identifier('string.strStartsWithCanBeUsed')
                ->line($funcCall->getStartLine())
                ->build(),
        ];
    }

    private function isComparisonWithZero(BinaryOp $node): bool
    {
        if ($node instanceof Identical || $node instanceof NotIdentical) {
            $left = $node->left;
            $right = $node->right;

            if (($left instanceof LNumber && $left->value === 0) ||
                ($right instanceof LNumber && $right->value === 0)) {
                return true;
            }
        }

        if ($node instanceof Equal || $node instanceof NotEqual) {
            $left = $node->left;
            $right = $node->right;

            if (($left instanceof LNumber && $left->value === 0) ||
                ($right instanceof LNumber && $right->value === 0)) {
                return true;
            }
        }

        return false;
    }

    private function findStrposCall(BinaryOp $node): ?FuncCall
    {
        $left = $node->left;
        $right = $node->right;

        if ($left instanceof FuncCall) {
            $functionName = $this->getFunctionName($left);
            if (in_array($functionName, ['strpos', 'mb_strpos'], true)) {
                return $left;
            }
        }

        if ($right instanceof FuncCall) {
            $functionName = $this->getFunctionName($right);
            if (in_array($functionName, ['strpos', 'mb_strpos'], true)) {
                return $right;
            }
        }

        return null;
    }

    private function getFunctionName(FuncCall $funcCall): ?string
    {
        if (!$funcCall->name instanceof Node\Name) {
            return null;
        }

        return strtolower($funcCall->name->toString());
    }

    private function getPrettyPrint(Node $node): string
    {
        // Simple pretty printing for common cases
        if ($node instanceof Node\Scalar\String_) {
            return "'" . addslashes($node->value) . "'";
        }
        if ($node instanceof Node\Expr\Variable) {
            return '$' . $node->name;
        }
        if ($node instanceof Node\Expr\PropertyFetch) {
            $var = $this->getPrettyPrint($node->var);
            $name = $node->name instanceof Node\Identifier ? $node->name->name : $this->getPrettyPrint($node->name);
            return $var . '->' . $name;
        }
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            $var = $this->getPrettyPrint($node->var);
            $dim = $node->dim !== null ? $this->getPrettyPrint($node->dim) : '';
            return $var . '[' . $dim . ']';
        }

        // Fallback - this is a simplified version, in production you might want to use PHP-Parser's Standard pretty printer
        return '...';
    }
}