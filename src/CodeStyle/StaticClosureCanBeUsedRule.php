<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Meldet Closures, die kein $this nutzen und statisch sein können.
 * Projektvorgabe: statische Closures bevorzugen.
 *
 * @implements Rule<Closure>
 */
final class StaticClosureCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return Closure::class;
    }

    /** @param Closure $node */
    public function processNode(Node $node, Scope $scope): array
    {
        // Bereits static? Dann OK
        if ($node->static) {
            return [];
        }

        // Wenn innerhalb der Closure $this nicht verwendet wird, kann sie static sein
        $finder = new NodeFinder();
        $thisUsages = $finder->find($node->stmts ?? [], static fn (Node $n) => $n instanceof Variable && $n->name === 'this');

        if ($thisUsages === []) {
            return [
                RuleErrorBuilder::message('Closure can be static; add static for clarity and performance.')
                    ->identifier('closure.staticPossible')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
