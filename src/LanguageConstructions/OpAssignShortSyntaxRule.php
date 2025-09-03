<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects assignment expressions that can be safely refactored using short syntax operators.
 *
 * This rule identifies patterns where a variable is assigned the result of a binary operation
 * on itself, and suggests using the compound assignment operators (+=, -=, *=, /=, %=, .=, etc.).
 *
 * Examples of detected patterns:
 * - $a = $a + 1; → $a += 1;
 * - $a = $a . 'test'; → $a .= 'test';
 * - $a = $a + $b + $c; → $a += $b + $c; (for chaining-safe operators)
 *
 * The rule handles chaining for operators that are safe to chain (+, ., *) and avoids
 * false positives for string array element manipulation.
 *
 * @implements Rule<Assign>
 */
final class OpAssignShortSyntaxRule implements Rule
{
    /**
     * Mapping of binary operators to their compound assignment equivalents.
     */
    private const OPERATOR_MAPPING = [
        BinaryOp\Plus::class => '+=',
        BinaryOp\Minus::class => '-=',
        BinaryOp\Mul::class => '*=',
        BinaryOp\Div::class => '/=',
        BinaryOp\Mod::class => '%=',
        BinaryOp\Concat::class => '.=',
        BinaryOp\BitwiseAnd::class => '&=',
        BinaryOp\BitwiseOr::class => '|=',
        BinaryOp\BitwiseXor::class => '^=',
        BinaryOp\ShiftLeft::class => '<<=',
        BinaryOp\ShiftRight::class => '>>=',
    ];

    /**
     * Operators that are safe for chaining (multiple operands).
     */
    private const CHAINING_SAFE_OPERATORS = [
        BinaryOp\Plus::class,
        BinaryOp\Concat::class,
        BinaryOp\Mul::class,
    ];

    public function getNodeType(): string
    {
        return Assign::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Assign) {
            return [];
        }

        $value = $node->expr;
        if (!$value instanceof BinaryOp) {
            return [];
        }

        $operator = $value::class;
        if (!isset(self::OPERATOR_MAPPING[$operator])) {
            return [];
        }

        $variable = $node->var;
        if (!$variable instanceof Variable && !$variable instanceof ArrayDimFetch) {
            return [];
        }

        // Check for string manipulation false positive
        if ($this->isStringManipulation($variable, $scope)) {
            return [];
        }

        $fragments = [];
        $candidate = $value->left;

        // Handle chaining by traversing the binary expression tree
        while ($candidate instanceof BinaryOp) {
            $current = $candidate;
            if ($current->right !== null) {
                $fragments[] = $current->right;
            }
            if ($current::class !== $operator) {
                break;
            }
            $candidate = $current->left;
        }

        // Check if the final candidate matches the assignment variable
        if (!$this->variablesEqual($candidate, $variable)) {
            return [];
        }

        // Add the right operand of the top-level binary expression
        if ($value->right !== null) {
            $fragments[] = $value->right;
        }

        // Check if shortening is allowed
        $canShorten = (count($fragments) === 1 || in_array($operator, self::CHAINING_SAFE_OPERATORS, true))
            && !$this->containsBinaryExpressions($fragments);

        if (!$canShorten) {
            return [];
        }

        // Generate the replacement suggestion
        $replacement = $this->generateReplacement($variable, $operator, $fragments);

        return [
            RuleErrorBuilder::message("Can be safely refactored as '{$replacement}'.")
                ->identifier('languageConstructions.opAssignShortSyntax')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Check if this is string manipulation that should be avoided.
     */
    private function isStringManipulation(Expr $variable, Scope $scope): bool
    {
        if (!$variable instanceof ArrayDimFetch) {
            return false;
        }

        $arrayVar = $variable->var;
        if (!$arrayVar instanceof Variable) {
            return false;
        }

        $type = $scope->getType($arrayVar);
        foreach ($type->getConstantStrings() as $constString) {
            if (str_contains($constString->getValue(), 'string')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two expressions represent the same variable.
     */
    private function variablesEqual(Expr $expr1, Expr $expr2): bool
    {
        if ($expr1 instanceof Variable && $expr2 instanceof Variable) {
            return $expr1->name === $expr2->name;
        }

        if ($expr1 instanceof ArrayDimFetch && $expr2 instanceof ArrayDimFetch) {
            return $this->variablesEqual($expr1->var, $expr2->var)
                && $this->expressionsEqual($expr1->dim, $expr2->dim);
        }

        return false;
    }

    /**
     * Check if two expressions are structurally equal.
     */
    private function expressionsEqual(?Expr $expr1, ?Expr $expr2): bool
    {
        if ($expr1 === null && $expr2 === null) {
            return true;
        }

        if ($expr1 === null || $expr2 === null) {
            return false;
        }

        // Simple equality check - in a full implementation, this would need
        // to handle more expression types and structural comparison
        return $expr1->getType() === $expr2->getType()
            && $expr1->getAttributes() === $expr2->getAttributes();
    }

    /**
     * Check if any fragment contains binary expressions.
     */
    private function containsBinaryExpressions(array $fragments): bool
    {
        $finder = new NodeFinder();
        foreach ($fragments as $fragment) {
            $binaryOps = $finder->findInstanceOf($fragment, BinaryOp::class);
            if (!empty($binaryOps)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate the replacement string for the compound assignment.
     */
    private function generateReplacement(Expr $variable, string $operator, array $fragments): string
    {
        $varText = $this->exprToString($variable);
        $opText = self::OPERATOR_MAPPING[$operator];

        $fragmentTexts = array_map(
            static fn(Expr $fragment) => $fragment->getType() === 'Expr_Variable'
                ? '$' . $fragment->name
                : $fragment->getType(), // Simplified - would need proper expression printing
            $fragments
        );

        return $varText . ' ' . $opText . ' ' . implode(' ' . substr($opText, 0, -1) . ' ', $fragmentTexts);
    }

    /**
     * Convert expression to string representation.
     */
    private function exprToString(Expr $expr): string
    {
        if ($expr instanceof Variable) {
            return '$' . $expr->name;
        }

        if ($expr instanceof ArrayDimFetch) {
            $varText = $this->exprToString($expr->var);
            $dimText = $expr->dim ? $this->exprToString($expr->dim) : '';
            return $varText . '[' . $dimText . ']';
        }

        // Fallback - in a real implementation, this would need to handle more cases
        return 'expr';
    }
}