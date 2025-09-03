<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Isset_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unnecessary isset arguments specification.
 *
 * This rule identifies isset() calls with multiple arguments where some arguments
 * are redundant because they are covered by array access expressions in other arguments.
 * For example: isset($array, $array[0], $array[0][0]) can be simplified to isset($array[0][0])
 * since checking isset on $array[0][0] already implies isset on $array and $array[0].
 *
 * This rule detects:
 * - isset() calls with redundant arguments that are covered by array access expressions
 * - Arguments that can be safely removed without changing the isset() behavior
 *
 * @implements Rule<Isset_>
 */
class UnnecessaryIssetArgumentsRule implements Rule
{
    public function getNodeType(): string
    {
        return Isset_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Isset_) {
            return [];
        }

        $arguments = $node->vars;
        if (count($arguments) <= 1) {
            return [];
        }

        $errors = [];
        $reported = [];

        for ($i = 0; $i < count($arguments); $i++) {
            $currentArg = $arguments[$i];

            if (!$currentArg instanceof ArrayDimFetch || in_array($i, $reported, true)) {
                continue;
            }

            // Collect all base variables from this array access expression
            $bases = $this->collectBaseVariables($currentArg);

            if ($bases === []) {
                continue;
            }

            // Check other arguments for redundancy
            for ($j = 0; $j < count($arguments); $j++) {
                if ($i === $j || in_array($j, $reported, true)) {
                    continue;
                }

                $otherArg = $arguments[$j];

                // Check if this argument is equivalent to any of the bases
                foreach ($bases as $base) {
                    if ($this->areExpressionsEquivalent($base, $otherArg)) {
                        $errors[] = RuleErrorBuilder::message(
                            'This argument can be skipped (handled by its array access).'
                        )
                            ->identifier('isset.unnecessaryArgument')
                            ->line($otherArg->getStartLine())
                            ->build();

                        $reported[] = $j;
                        break 2; // Break out of both loops since we found a match
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Collect all base variables from an array access expression.
     * For $a['b']['c'], this returns [$a['b'], $a]
     *
     * @param ArrayDimFetch $arrayDimFetch
     * @return Node[]
     */
    private function collectBaseVariables(ArrayDimFetch $arrayDimFetch): array
    {
        $bases = [];
        $current = $arrayDimFetch;

        while ($current instanceof ArrayDimFetch) {
            $base = $current->var;
            if ($base !== null) {
                $bases[] = $base;
            }
            $current = $base instanceof ArrayDimFetch ? $base : null;
        }

        return $bases;
    }

    /**
     * Check if two expressions are structurally equivalent.
     * This is a simplified equivalence check for isset argument comparison.
     *
     * @param Node $expr1
     * @param Node $expr2
     * @return bool
     */
    private function areExpressionsEquivalent(Node $expr1, Node $expr2): bool
    {
        // For simple variables, check if they have the same name
        if ($expr1 instanceof Node\Expr\Variable && $expr2 instanceof Node\Expr\Variable) {
            return $expr1->name === $expr2->name;
        }

        // For array access expressions, check recursively
        if ($expr1 instanceof ArrayDimFetch && $expr2 instanceof ArrayDimFetch) {
            // Check if the base variables are equivalent
            if (!$this->areExpressionsEquivalent($expr1->var, $expr2->var)) {
                return false;
            }

            // Check if the dimension expressions are equivalent
            return $this->areExpressionsEquivalent($expr1->dim, $expr2->dim);
        }

        // For simple scalars or literals, use string representation comparison
        if (($expr1 instanceof Node\Scalar || $expr1 instanceof Node\Expr\ConstFetch) &&
            ($expr2 instanceof Node\Scalar || $expr2 instanceof Node\Expr\ConstFetch)) {
            return $expr1->getAttribute('rawValue', '') === $expr2->getAttribute('rawValue', '');
        }

        return false;
    }
}