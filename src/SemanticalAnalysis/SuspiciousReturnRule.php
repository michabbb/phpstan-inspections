<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Expr\Throw_ as ExprThrow;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects suspicious return statements in finally blocks.
 *
 * This rule identifies return statements inside finally blocks that void all return
 * and throw statements from the try-block. When a return statement is placed in a
 * finally block and the corresponding try block contains return or throw statements,
 * those statements become ineffective because the finally block's return will always
 * execute and override any values or exceptions from the try block.
 *
 * This can lead to:
 * - Lost return values from try block
 * - Suppressed exceptions from try block
 * - Unexpected program flow
 *
 * @implements Rule<TryCatch>
 */
final class SuspiciousReturnRule implements Rule
{
    public function getNodeType(): string
    {
        return TryCatch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof TryCatch) {
            return [];
        }

        // Check if there's a finally block
        if ($node->finally === null) {
            return [];
        }

        // Check if the try block contains return or throw statements
        if (!$this->tryBlockHasReturnOrThrow($node)) {
            return [];
        }

        // Find return statements in the finally block
        $nodeFinder = new NodeFinder();
        $returnStatements = $nodeFinder->findInstanceOf($node->finally->stmts, Return_::class);

        if (empty($returnStatements)) {
            return [];
        }

        $errors = [];
        foreach ($returnStatements as $returnStmt) {
            $errors[] = RuleErrorBuilder::message(
                'Voids all return and throw statements from the try-block (returned values and exceptions are lost).'
            )
            ->identifier('controlFlow.suspiciousReturn')
            ->line($returnStmt->getStartLine())
            ->build();
        }

        return $errors;
    }

    private function tryBlockHasReturnOrThrow(TryCatch $tryCatch): bool
    {
        $nodeFinder = new NodeFinder();

        // Check try block for return statements (direct and nested)
        $hasReturnInTry = $this->hasReturnInStatements($tryCatch->stmts);
        if ($hasReturnInTry) {
            return true;
        }

        // Check catch blocks for return statements (direct and nested)
        foreach ($tryCatch->catches as $catch) {
            $hasReturnInCatch = $this->hasReturnInStatements($catch->stmts);
            if ($hasReturnInCatch) {
                return true;
            }
        }

        return false;
    }

    private function hasReturnInStatements(array $statements): bool
    {
        $nodeFinder = new NodeFinder();

        foreach ($statements as $stmt) {
            // Check if this statement itself is a return
            if ($stmt instanceof Return_) {
                return true;
            }

            // Check nested statements (e.g., in if-else, loops, etc.)
            $nestedNodes = $nodeFinder->findInstanceOf([$stmt], Node::class);
            $returnStmts = $nodeFinder->findInstanceOf($nestedNodes, Return_::class);

            if (!empty($returnStmts)) {
                return true;
            }
        }

        return false;
    }}
