<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\While_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\Closure;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects continue statements inside switch statements that are within loops.
 *
 * In PHP, 'continue' inside a 'switch' behaves as 'break'. To continue the external loop,
 * use 'continue 2;' instead. This rule identifies such patterns and suggests the correct usage.
 *
 * This rule detects:
 * - continue statements without explicit level specification inside switch statements
 * - switch statements that are nested within loop constructs (for, foreach, while, do-while)
 * - suggests using 'continue 2;' to properly continue the outer loop
 *
 * @implements Rule<Node>
 */
class SwitchContinuationInLoopRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Continue_) {
            return [];
        }

        // Skip if continue has an explicit level (e.g., continue 2;)
        if ($node->num !== null) {
            return [];
        }

        // Check if we're inside a switch that's inside a loop
        if ($this->isInSwitchWithinLoop($node)) {
            return [
                RuleErrorBuilder::message(
                    "In PHP, 'continue' inside a 'switch' behaves as 'break'. Use 'continue 2;' to continue the external loop."
                )
                ->identifier('switch.continuationInLoop')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        return [];
    }

    private function isInSwitchWithinLoop(Node $node): bool
    {
        if (!$node->hasAttribute('parentStmtTypes')) {
            return false;
        }

        $parentStmtTypes = $node->getAttribute('parentStmtTypes');
        if (!is_array($parentStmtTypes)) {
            return false;
        }

        // Check if there's both a Switch and a Loop in the parent chain
        $hasSwitch = false;
        $hasLoop = false;

        foreach ($parentStmtTypes as $stmtType) {
            if ($stmtType === Switch_::class) {
                $hasSwitch = true;
            } elseif ($this->isLoopClass($stmtType)) {
                $hasLoop = true;
            }
        }

        return $hasSwitch && $hasLoop;
    }

    private function isLoopClass(string $className): bool
    {
        return in_array($className, [
            For_::class,
            Foreach_::class,
            While_::class,
            Do_::class,
        ], true);
    }

    private function isLoop(Node $node): bool
    {
        return $node instanceof For_
            || $node instanceof Foreach_
            || $node instanceof While_
            || $node instanceof Do_;
    }
}