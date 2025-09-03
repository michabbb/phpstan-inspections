<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Npe;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\MixedType;
use PHPStan\Type\TemplateMixedType;

/**
 * Detects potential null pointer exceptions with sophisticated control flow analysis.
 *
 * This rule implements logic similar to the Java IntelliJ IDEA inspection, including:
 * - Control flow awareness (respects null checks, instanceof, assertions)
 * - Context-sensitive analysis (ignores guarded scenarios)
 * - False positive prevention (nullsafe operators, isset, empty, coalescing)
 *
 * @implements Rule<Expr>
 */
final class NullPointerExceptionRule implements Rule
{
    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Step 0: Fast-path skips for non-dereference operations
        if (!$this->isDereference($node)) {
            return [];
        }

        // If the expression itself or any nested part of it uses a nullsafe operator,
        // the entire expression is considered safe from NPEs that this rule checks for.
        $finder = new NodeFinder();
        $hasNullsafe = $finder->findFirst($node, function(Node $subNode) {
            return $subNode instanceof NullsafeMethodCall || $subNode instanceof NullsafePropertyFetch;
        });

        if ($hasNullsafe !== null) {
            return [];
        }

        // Step 1: Get the root expression being dereferenced
        $rootExpr = $this->getRootExpression($node);
        if ($rootExpr === null) {
            return [];
        }

        // Step 2: Check if the type can possibly be null
        $type = $scope->getType($rootExpr);
        if (!$this->isPossiblyNull($type)) {
            return [];
        }

        // Step 3: Check if this dereference is guarded/protected (following Java logic exactly)
        if ($this->isProtectedByNullGuards($node, $rootExpr)) {
            return [];
        }

        // Step 4: Emit error
        return [
            RuleErrorBuilder::message(
                'Possible NullPointerException. Variable/expression might be null before dereferencing.'
            )
            ->identifier('null.pointerException')
            ->tip('Add a null check (e.g., `if ($var !== null)`) or use the nullsafe operator (`$var?->method()`).')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    private function isDereference(Node $node): bool
    {
        return $node instanceof MethodCall
            || $node instanceof PropertyFetch
            || $node instanceof ArrayDimFetch
            || $node instanceof StaticCall
            || $node instanceof Clone_
            || ($node instanceof FuncCall && $node->name instanceof Expr); // __invoke calls
    }

    private function getRootExpression(Node $node): ?Expr
    {
        if ($node instanceof MethodCall || $node instanceof PropertyFetch) {
            return $node->var;
        }
        if ($node instanceof ArrayDimFetch) {
            return $node->var;
        }
        if ($node instanceof Clone_) {
            return $node->expr;
        }
        if ($node instanceof FuncCall && $node->name instanceof Expr) {
            return $node->name; // __invoke on variable
        }
        return null;
    }

    private function isPossiblyNull(\PHPStan\Type\Type $type): bool
    {
        // Mixed/Unknown → don't warn, too noisy
        if ($type instanceof MixedType && !$type instanceof TemplateMixedType) {
            return false;
        }

        // If PHPStan is absolutely sure it's never null, trust it
        if ($type->isNull()->no()) {
            return false;
        }

        // Use the correct way to check for null in unions
        return TypeCombinator::containsNull($type);
    }

    /**
     * Check if this dereference is protected by null guards (Java logic port)
     */
    private function isProtectedByNullGuards(Node $node, Expr $variable): bool
    {
        $parent = $node->getAttribute('parent');
        if ($parent === null) {
            return false;
        }

        // instanceof, implicit null comparisons
        if ($parent instanceof Node\Expr\BinaryOp) {
            if ($parent instanceof Node\Expr\Instanceof_) {
                return true;
            }
            // Equality comparisons with null
            if ($parent instanceof Node\Expr\BinaryOp\Equal
                || $parent instanceof Node\Expr\BinaryOp\NotEqual
                || $parent instanceof Node\Expr\BinaryOp\Identical
                || $parent instanceof Node\Expr\BinaryOp\NotIdentical) {

                $otherOperand = $parent->left === $node ? $parent->right : $parent->left;
                if ($otherOperand instanceof Node\Expr\ConstFetch
                    && strtolower($otherOperand->name->toString()) === 'null') {
                    return true;
                }
            }
        }

        // non-implicit null comparisons (exact Java logic)
        if ($parent instanceof Node\Expr\Empty_
            || $parent instanceof Node\Expr\Isset_
            || $this->isUsedAsLogicalOperand($node)) {
            return true;
        }

        // Nullsafe and coalescing
        return $this->isInNullCoalescing($node);
    }

    /**
     * A robust port of Java's ExpressionSemanticUtil.isUsedAsLogicalOperand()
     */
    private function isUsedAsLogicalOperand(Node $node): bool
    {
        $current = $node;

        while ($current !== null) {
            $parent = $current->getAttribute('parent');

            if ($parent === null) {
                break;
            }

            // Direct boolean operations (&&, ||, and, or)
            if ($parent instanceof Node\Expr\BinaryOp\BooleanAnd ||
                $parent instanceof Node\Expr\BinaryOp\BooleanOr ||
                $parent instanceof Node\Expr\BinaryOp\LogicalAnd ||
                $parent instanceof Node\Expr\BinaryOp\LogicalOr) {
                return true;
            }

            // Unary boolean operations (!)
            if ($parent instanceof Node\Expr\BooleanNot) {
                return true;
            }

            // Conditional statements where the node is the entire condition
            if (($parent instanceof Node\Stmt\If_ ||
                 $parent instanceof Node\Stmt\ElseIf_ ||
                 $parent instanceof Node\Stmt\While_) && $parent->cond === $current) {
                return true;
            }

            // For loop condition
            if ($parent instanceof Node\Stmt\For_ && in_array($current, $parent->cond, true)) {
                return true;
            }

            // Ternary operator condition
            if ($parent instanceof Node\Expr\Ternary && $parent->cond === $current) {
                return true;
            }

            // Cast to boolean
            if ($parent instanceof Node\Expr\Cast\Bool_) {
                return true;
            }

            // Stop traversal at statement boundaries that aren't the control structures themselves
            if ($parent instanceof Node\Stmt &&
                !($parent instanceof Node\Stmt\If_) &&
                !($parent instanceof Node\Stmt\ElseIf_) &&
                !($parent instanceof Node\Stmt\While_) &&
                !($parent instanceof Node\Stmt\For_)) {
                break;
            }

            $current = $parent;
        }

        return false;
    }

    private function isInNullCoalescing(Node $node): bool
    {
        $parent = $node->getAttribute('parent');
        while ($parent !== null) {
            if ($parent instanceof Node\Expr\BinaryOp\Coalesce) {
                // Only protected if we're on the left side of ??
                return $parent->left === $node || $this->isAncestorOf($parent->left, $node);
            }
            if ($parent instanceof Node\Stmt) {
                break;
            }
            $parent = $parent->getAttribute('parent');
        }
        return false;
    }

    private function isAncestorOf(Node $ancestor, Node $descendant): bool
    {
        $current = $descendant->getAttribute('parent');
        while ($current !== null && $current !== $ancestor) {
            $current = $current->getAttribute('parent');
        }
        return $current === $ancestor;
    }
}
