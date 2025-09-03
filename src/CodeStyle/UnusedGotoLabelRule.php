<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\Label;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unused goto labels in functions and methods.
 *
 * This rule identifies goto labels that are defined but never referenced by
 * any goto statement within the same function or method scope. Unused labels
 * indicate dead code that should be removed for cleaner code.
 *
 * @implements Rule<Label>
 */
final class UnusedGotoLabelRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Only analyze inside functions/methods
        if (!($node instanceof Function_ || $node instanceof ClassMethod)) {
            return [];
        }

        $stmts = $node->getStmts() ?? [];
        $finder = new \PhpParser\NodeFinder();

        // Collect labels and gotos
        $labels = $finder->find($stmts, static fn(Node $n): bool => $n instanceof Label);
        if ($labels === []) {
            return [];
        }
        $gotos = $finder->find($stmts, static fn(Node $n): bool => $n instanceof Goto_);

        $errors = [];
        foreach ($labels as $labelNode) {
            $labelName = is_string($labelNode->name) ? $labelNode->name : $labelNode->name->toString();
            $used = false;
            foreach ($gotos as $goto) {
                $gotoName = is_string($goto->name) ? $goto->name : $goto->name->toString();
                if ($gotoName === $labelName) {
                    $used = true;
                    break;
                }
            }
            if (!$used) {
                $errors[] = RuleErrorBuilder::message('The label is not used.')
                    ->identifier('goto.unusedLabel')
                    ->line($labelNode->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}
