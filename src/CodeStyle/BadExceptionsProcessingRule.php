<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\Try_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects badly organized exception handling patterns.
 *
 * This rule identifies:
 * - Try blocks with more than 3 statements that should be extracted into separate methods
 * - Catch blocks where the exception variable is not used, indicating poor error handling
 *
 * @implements Rule<Node>
 */
class BadExceptionsProcessingRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        if ($node instanceof Try_) {
            $errors = array_merge($errors, $this->analyzeTryBlock($node));
        }

        if ($node instanceof Catch_) {
            $errors = array_merge($errors, $this->analyzeCatchBlock($node));
        }

        return $errors;
    }

    /**
     * @param Try_ $tryStatement
     * @return list<array>
     */
    private function analyzeTryBlock(Try_ $tryStatement): array
    {
        $expressionCount = $this->countExpressionsInStatements($tryStatement->stmts);

        if ($expressionCount > 3) {
            return [
                RuleErrorBuilder::message(
                    "It is possible that some of the statements contained in the try block can be extracted into their own methods or functions (we recommend that you do not include more than three statements per try block)."
                )
                    ->identifier('exceptions.badlyOrganized.tryBlock')
                    ->line($tryStatement->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * @param Catch_ $catchStatement
     * @return list<array>
     */
    private function analyzeCatchBlock(Catch_ $catchStatement): array
    {
        $exceptionVar = $catchStatement->var;
        if ($exceptionVar === null || !($exceptionVar instanceof Variable)) {
            return [];
        }

        $variableName = $exceptionVar->name;
        if (!is_string($variableName)) {
            return [];
        }

        $isVariableUsed = $this->isVariableUsedInStatements($variableName, $catchStatement->stmts);
        if ($isVariableUsed) {
            return [];
        }

        $expressionCount = $this->countExpressionsInStatements($catchStatement->stmts);

        if ($expressionCount === 0) {
            return [
                RuleErrorBuilder::message(
                    "The exception being ignored, please don't fail silently and at least log it."
                )
                    ->identifier('exceptions.badlyOrganized.catchSilent')
                    ->line($exceptionVar->getStartLine())
                    ->build(),
            ];
        }

        return [
            RuleErrorBuilder::message(
                "The exception being ignored, please log it or use chained exceptions."
            )
                ->identifier('exceptions.badlyOrganized.catchUnused')
                ->line($exceptionVar->getStartLine())
                ->build(),
        ];
    }

    /**
     * @param array<Node\Stmt> $statements
     * @return int
     */
    private function countExpressionsInStatements(array $statements): int
    {
        $count = 0;

        foreach ($statements as $statement) {
            $count += $this->countExpressionsInStatement($statement);
        }

        return $count;
    }

    /**
     * @param Node\Stmt $statement
     * @return int
     */
    private function countExpressionsInStatement(Node\Stmt $statement): int
    {
        if ($statement instanceof Node\Stmt\Expression) {
            return 1;
        }

        // For other statement types, we count them as 1
        // This is a simplified approach - in a real implementation,
        // you might want to traverse deeper into complex statements
        return 1;
    }

    /**
     * @param string $variableName
     * @param array<Node\Stmt> $statements
     * @return bool
     */
    private function isVariableUsedInStatements(string $variableName, array $statements): bool
    {
        $nodeFinder = new NodeFinder();

        $variables = $nodeFinder->find($statements, static function (Node $node) use ($variableName): bool {
            return $node instanceof Variable
                && $node->name === $variableName;
        });

        return count($variables) > 0;
    }
}