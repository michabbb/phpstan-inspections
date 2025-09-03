<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects nested assignment expressions that can lead to confusion and bugs.
 *
 * This rule identifies chained assignments like $a = $b = $c, which can be error-prone
 * and harder to read. It suggests using separate assignment statements for better
 * clarity and to avoid potential typos that could change the intended logic.
 *
 * @implements Rule<Assign>
 */
final class NestedAssignmentsUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return Assign::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Assign) {
            return [];
        }

        // Check if the right-hand side of the current assignment is another assignment.
        // This identifies the outermost assignment in a nested chain.
        if ($node->expr instanceof Assign) {
            return [
                RuleErrorBuilder::message(
                    'Using dedicated assignment would be more reliable (e.g \'$... = $... + 10\' can be mistyped as `$... = $... = 10`).'
                )
                ->identifier('assignment.nested')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        return [];
    }
}
