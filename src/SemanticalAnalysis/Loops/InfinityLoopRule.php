<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Loops;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects infinity loops caused by accidental recursion in methods.
 *
 * This rule identifies:
 * - Methods that call themselves recursively without proper termination conditions
 * - Single-statement methods that return the result of calling the same method
 * - Methods using $this, self, or static to call themselves
 *
 * This can cause stack overflow errors and infinite loops at runtime.
 *
 * @implements Rule<ClassMethod>
 */
class InfinityLoopRule implements Rule
{
    private const string MESSAGE = 'Causes infinity loop.';

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        // Skip abstract methods
        if ($node->isAbstract()) {
            return [];
        }

        // Check if method has a body
        if ($node->stmts === null) {
            return [];
        }

        // Method must have exactly one statement
        if (count($node->stmts) !== 1) {
            return [];
        }

        $statement = $node->stmts[0];

        // Get the expression from return or expression statement
        $expression = null;
        if ($statement instanceof Return_ && $statement->expr !== null) {
            $expression = $statement->expr;
        } elseif ($statement instanceof Expression) {
            $expression = $statement->expr;
        }

        if ($expression === null) {
            return [];
        }

        // Check if the expression is a method call
        if (!$expression instanceof MethodCall) {
            return [];
        }

        // Check if the method name matches the current method
        if (!$expression->name instanceof Node\Identifier) {
            return [];
        }

        $calledMethodName = $expression->name->toString();
        $currentMethodName = $node->name->toString();

        if ($calledMethodName !== $currentMethodName) {
            return [];
        }

        // Check if the class reference is $this, self, or static
        if (!$this->isSelfReference($expression->var)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('methodCall.infiniteRecursion')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isSelfReference(Node\Expr $var): bool
    {
        // Check for $this
        if ($var instanceof Variable && $var->name === 'this') {
            return true;
        }

        // Check for self or static
        if ($var instanceof Name) {
            $name = $var->toString();
            return $name === 'self' || $name === 'static';
        }

        return false;
    }
}