<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests using Elvis operator (?:) or null coalescing operator (??) for simpler ternary expressions.
 *
 * This rule detects ternary expressions that can be simplified using the Elvis operator (?:)
 * or null coalescing operator (??). It identifies patterns like:
 * - $a ? $a : $b → $a ?: $b
 * - isset($a) ? $a : $b → $a ?? $b
 * - $a !== null ? $a : $b → $a ?? $b
 *
 * @implements Rule<Ternary>
 */
final class ElvisOperatorCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return Ternary::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Ternary) {
            return [];
        }

        // Case: $a ? $a : $b;  -> $a ?: $b;
        if ($node->cond instanceof Expr && $node->if instanceof Expr && $this->areExprsEquivalent($node->if, $node->cond)) {
            return [
                RuleErrorBuilder::message('Elvis operator (?:) can be used.')
                    ->identifier('languageConstructions.elvisOperatorCanBeUsed')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        // Case: isset($a) ? $a : $b; -> $a ?? $b;
        if ($node->cond instanceof FuncCall && $node->cond->name instanceof Name && (string) $node->cond->name === 'isset') {
            if (count($node->cond->args) === 1 && $node->if instanceof Expr && $this->areExprsEquivalent($node->if, $node->cond->args[0]->value)) {
                return [
                    RuleErrorBuilder::message('Null coalescing operator (??) can be used.')
                        ->identifier('languageConstructions.elvisOperatorCanBeUsed')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        // Case: $a !== null ? $a : $b; -> $a ?? $b;
        if ($node->cond instanceof NotIdentical && $node->cond->right instanceof Expr\ConstFetch && (string) $node->cond->right->name === 'null') {
            if ($node->if instanceof Expr && $this->areExprsEquivalent($node->if, $node->cond->left)) {
                return [
                    RuleErrorBuilder::message('Null coalescing operator (??) can be used.')
                        ->identifier('languageConstructions.elvisOperatorCanBeUsed')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function areExprsEquivalent(Expr $a, Expr $b): bool
    {
        // Variables with the same name
        if ($a instanceof Expr\Variable && $b instanceof Expr\Variable) {
            $aName = is_string($a->name) ? $a->name : null;
            $bName = is_string($b->name) ? $b->name : null;
            return $aName !== null && $aName === $bName;
        }

        // Simple array dim fetch like $x[0] vs $x[0]
        if ($a instanceof Expr\ArrayDimFetch && $b instanceof Expr\ArrayDimFetch) {
            return $this->areExprsEquivalent($a->var, $b->var)
                && (($a->dim === null && $b->dim === null) || ($a->dim !== null && $b->dim !== null && $this->areScalarNodesEqual($a->dim, $b->dim)));
        }

        // Property fetch $obj->prop equivalence
        if ($a instanceof Expr\PropertyFetch && $b instanceof Expr\PropertyFetch) {
            $aProp = $a->name instanceof Node\Identifier ? $a->name->toString() : null;
            $bProp = $b->name instanceof Node\Identifier ? $b->name->toString() : null;
            return $aProp !== null && $aProp === $bProp && $this->areExprsEquivalent($a->var, $b->var);
        }

        return false;
    }

    private function areScalarNodesEqual(?Node $a, ?Node $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        if ($a instanceof Node\Scalar && $b instanceof Node\Scalar) {
            // Compare their printed values
            return $a->getAttribute('rawValue') === $b->getAttribute('rawValue')
                || (method_exists($a, '__toString') && method_exists($b, '__toString') && (string) $a === (string) $b)
                || ($a instanceof Node\Scalar\LNumber && $b instanceof Node\Scalar\LNumber && $a->value === $b->value)
                || ($a instanceof Node\Scalar\String_ && $b instanceof Node\Scalar\String_ && $a->value === $b->value);
        }

        // Fallback: strict type and string representation
        return get_class($a) === get_class($b) && (string) $a === (string) $b;
    }
}
