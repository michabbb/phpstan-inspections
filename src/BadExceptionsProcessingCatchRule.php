<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects badly organized exception handling in catch blocks.
 *
 * This rule identifies catch blocks where the exception variable is not used,
 * which can indicate poor exception handling practices. It distinguishes between:
 * - Empty catch blocks that silently ignore exceptions
 * - Catch blocks with statements that don't use the caught exception
 *
 * Suggests either logging the exception or using chained exceptions.
 *
 * @implements Rule<Catch_>
 */
final class BadExceptionsProcessingCatchRule implements Rule
{
    public const string MESSAGE_FAIL_SILENTLY = 'The exception being ignored, please don\'t fail silently and at least log it.';
    public const string MESSAGE_CHAINED_EXCEPTION = 'The exception being ignored, please log it or use chained exceptions.';

    public function getNodeType(): string
    {
        return \PhpParser\Node\Stmt\Catch_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Catch_) {
            return [];
        }

        // Check if there's an exception variable
        if (empty($node->var) || !$node->var instanceof Node\Expr\Variable) {
            return [];
        }

        $exceptionVariableName = $node->var->name;
        if (!is_string($exceptionVariableName)) {
            return [];
        }

        // Check if the exception variable is used in the catch block
        $isVariableUsed = $this->isExceptionVariableUsed($node->stmts, $exceptionVariableName);
        $statementCount = $this->countStatementsInCatchBlock($node->stmts);

        if (!$isVariableUsed) {
            if ($statementCount === 0) {
                // Empty catch block - fail silently
                return [
                    RuleErrorBuilder::message(self::MESSAGE_FAIL_SILENTLY)
                        ->identifier('exception.catchFailSilently')
                        ->line($node->var->getStartLine())
                        ->build(),
                ];
            } else {
                // Catch block with statements but unused exception variable
                return [
                    RuleErrorBuilder::message(self::MESSAGE_CHAINED_EXCEPTION)
                        ->identifier('exception.catchUnusedException')
                        ->line($node->var->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    /**
     * Check if the exception variable is used anywhere in the catch block statements.
     *
     * @param Node\Stmt[] $statements
     */
    private function isExceptionVariableUsed(array $statements, string $variableName): bool
    {
        $visitor = new class($variableName) extends NodeVisitorAbstract {
            private string $variableName;
            public bool $isUsed = false;

            public function __construct(string $variableName)
            {
                $this->variableName = $variableName;
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\Expr\Variable && is_string($node->name) && $node->name === $this->variableName) {
                    $this->isUsed = true;
                }
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($statements);

        return $visitor->isUsed;
    }

    /**
     * Count the number of statements in a catch block.
     * This replicates the logic from ExpressionSemanticUtil.countExpressionsInGroup
     * in the Java inspector.
     *
     * @param Node\Stmt[] $statements
     */
    private function countStatementsInCatchBlock(array $statements): int
    {
        // Filter out comments and empty statements
        $actualStatements = array_filter($statements, static function($stmt) {
            // Comments are not included in $statements array in PHP-Parser
            // Empty statements might be Nop nodes
            return !$stmt instanceof Node\Stmt\Nop;
        });
        
        return count($actualStatements);
    }
}