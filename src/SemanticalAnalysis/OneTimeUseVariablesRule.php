<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Rule to detect redundant one-time-use variables
 * @implements Rule<Node>
 */
class OneTimeUseVariablesRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Node\Stmt\Function_ && !$node instanceof Node\Stmt\ClassMethod) {
            return [];
        }

        $statements = $node->getStmts();
        if ($statements === null || count($statements) < 2) {
            return [];
        }

        return $this->analyzeStatements($statements);
    }

    private function analyzeStatements(array $statements): array
    {
        $errors = [];

        for ($i = 0; $i < count($statements) - 1; $i++) {
            $current = $statements[$i];
            $next = $statements[$i + 1];

            // Check for assignment followed by return/throw
            if (!$current instanceof Expression || !$current->expr instanceof Assign) {
                continue;
            }

            // Check if next statement is return or throw (throw is always wrapped in Expression)
            $nextExpr = null;
            if ($next instanceof Return_) {
                $nextExpr = $next->expr;
            } elseif ($next instanceof Expression && $next->expr instanceof Throw_) {
                $nextExpr = $next->expr->expr;
            } else {
                continue;
            }

            $assignment = $current->expr;
            if (!$assignment->var instanceof Variable || !is_string($assignment->var->name)) {
                continue;
            }

            $variableName = $assignment->var->name;

            // Check if return/throw uses the same variable
            if (!$nextExpr instanceof Variable || $nextExpr->name !== $variableName) {
                continue;
            }

            // Check if variable is used only twice (assignment + usage)
            $finder = new NodeFinder();
            $usages = $finder->find($statements, function (Node $n) use ($variableName) {
                return $n instanceof Variable && $n->name === $variableName;
            });

            if (count($usages) === 2) {
                $errors[] = RuleErrorBuilder::message(sprintf('Variable $%s is redundant.', $variableName))
                    ->identifier('variable.redundant')
                    ->line($next->expr->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}