<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Pdo;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Detects inefficient PDO API usage patterns.
 *
 * This rule identifies when PDO::prepare() is followed by execute() without parameters,
 * which can be replaced with the more efficient PDO::query() method. It helps optimize
 * database operations by suggesting better PDO API usage patterns.
 *
 * @implements Rule<MethodCall>
 */
class PdoApiUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        $errors = [];

        // Rule 1: Suggest using query() instead of prepare() when used without parameters
        // We'll detect patterns where prepare() is called with a plain string and suggest query()
        if ($this->isPdoMethodCall($node, $scope, 'prepare')) {
            $args = $node->getArgs();
            if (count($args) > 0) {
                $firstArg = $args[0]->value;
                // Check if it's a static string without placeholders
                if ($firstArg instanceof Node\Scalar\String_) {
                    $query = $firstArg->value;
                    // If the query doesn't contain placeholders, suggest query() instead
                    if (!str_contains($query, '?') && !str_contains($query, ':')) {
                        $errors[] = RuleErrorBuilder::message(
                            'Using PDO::prepare() without placeholders. Consider using PDO::query() instead for better performance.'
                        )
                            ->identifier('pdo.unnecessaryPrepare')
                            ->line($node->getStartLine())
                            ->build();
                    }
                }
            }
        }

        // Rule 2: Detect query() calls that might not check for errors
        if ($this->isPdoMethodCall($node, $scope, 'query')) {
            // We can suggest checking the return value, but without complex flow analysis
            // we'll just provide general guidance
            $parent = $node->getAttribute('parent');
            if ($parent === null) {
                // Query result is not being used at all
                $errors[] = RuleErrorBuilder::message(
                    'PDO::query() result should be checked for errors or assigned to a variable.'
                )
                    ->identifier('pdo.queryResultNotUsed')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    private function isPdoMethodCall(MethodCall $node, Scope $scope, string $methodName): bool
    {
        $calledOnType = $scope->getType($node->var);
        return (new ObjectType('PDO'))->isSuperTypeOf($calledOnType)->yes() &&
               $this->isMethodName($node, $methodName);
    }

    private function isPdoStatementMethodCall(MethodCall $node, Scope $scope, string $methodName): bool
    {
        $calledOnType = $scope->getType($node->var);
        return (new ObjectType('PDOStatement'))->isSuperTypeOf($calledOnType)->yes() &&
               $this->isMethodName($node, $methodName);
    }

    private function isMethodName(MethodCall $node, string $methodName): bool
    {
        return $node->name instanceof Node\Identifier && $node->name->name === $methodName;
    }
}
