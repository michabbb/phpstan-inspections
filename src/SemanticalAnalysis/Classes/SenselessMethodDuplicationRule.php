<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects child methods that are exactly identical to their parent methods.
 *
 * This rule identifies:
 * - Child methods with identical code to parent methods (code duplication)
 * - Suggests removing the method if access levels are the same
 * - Suggests making it a proxy call if access levels differ
 *
 * Only analyzes methods with 20 expressions or fewer for performance.
 *
 * @implements Rule<ClassMethod>
 */
class SenselessMethodDuplicationRule implements Rule
{
    public const int MAX_METHOD_SIZE = 20;

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        // Skip abstract, deprecated, or private methods
        if ($node->isAbstract() || $this->isPrivate($node)) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || $classReflection->isTrait() || $classReflection->isInterface()) {
            return [];
        }

        // Get parent class and method
        $parentClass = $this->getParentClass($classReflection);
        if ($parentClass === null) {
            return [];
        }

        $parentMethod = $this->getParentMethod($parentClass, $node->name->toString(), $scope);
        if ($parentMethod === null || $parentMethod->isAbstract() || $parentMethod->isPrivate()) {
            return [];
        }

        // Check method size limit
        $methodStatements = $this->getMethodStatements($node);
        if ($methodStatements === null) {
            return [];
        }

        $methodExpressionCount = $this->countExpressions($methodStatements);
        if ($methodExpressionCount === 0 || $methodExpressionCount > self::MAX_METHOD_SIZE) {
            return [];
        }

        // For this simplified implementation, we'll focus on simple proxy methods
        // that just call parent::method() with the same arguments
        if (!$this->isSimpleProxyMethod($node, $methodStatements)) {
            return [];
        }

        // Determine error message based on access levels
        $methodAccess = $this->getMethodAccess($node);
        $parentAccess = $this->getParentMethodAccess($parentMethod);

        if ($methodAccess === $parentAccess) {
            return [
                RuleErrorBuilder::message(
                    sprintf("'%s' method can be dropped, as it is identical to parent's one.", $node->name->toString())
                )
                    ->identifier('method.duplicateIdentical')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' method should call parent's one instead of duplicating code.", $node->name->toString())
            )
                ->identifier('method.duplicateProxy')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isPrivate(ClassMethod $method): bool
    {
        return $method->isPrivate();
    }

    private function getParentClass(ClassReflection $classReflection): ?ClassReflection
    {
        $parents = $classReflection->getParents();
        return $parents[0] ?? null;
    }

    private function getParentMethod(ClassReflection $parentClass, string $methodName, Scope $scope): ?MethodReflection
    {
        if (!$parentClass->hasMethod($methodName)) {
            return null;
        }

        return $parentClass->getMethod($methodName, $scope);
    }

    private function getMethodStatements(ClassMethod $method): ?array
    {
        if ($method->stmts === null) {
            return null;
        }

        return $method->stmts;
    }

    private function countExpressions(array $statements): int
    {
        $count = 0;
        foreach ($statements as $statement) {
            $count += $this->countExpressionsInStatement($statement);
        }
        return $count;
    }

    private function countExpressionsInStatement(Node $statement): int
    {
        $finder = new NodeFinder();
        $expressions = $finder->findInstanceOf($statement, Node\Expr::class);
        return count($expressions);
    }

    private function isSimpleProxyMethod(ClassMethod $method, array $statements): bool
    {
        if (count($statements) !== 1) {
            return false;
        }

        $statement = $statements[0];
        if (!$statement instanceof Return_) {
            return false;
        }

        if ($statement->expr === null) {
            return false;
        }

        if (!$statement->expr instanceof StaticCall) {
            return false;
        }

        $staticCall = $statement->expr;

        if (!$staticCall->class instanceof Name || $staticCall->class->toString() !== 'parent') {
            return false;
        }

        if (!$staticCall->name instanceof Node\Identifier ||
            $staticCall->name->toString() !== $method->name->toString()) {
            return false;
        }

        $methodParams = $method->params;
        $callArgs = $staticCall->args;

        if (count($methodParams) !== count($callArgs)) {
            return false;
        }

        foreach ($methodParams as $i => $param) {
            if (!$param->var instanceof Variable) {
                return false;
            }

            $arg = $callArgs[$i]->value;
            if (!$arg instanceof Variable) {
                return false;
            }

            if ($param->var->name !== $arg->name) {
                return false;
            }
        }

        return true;
    }

    private function getMethodAccess(ClassMethod $method): string
    {
        if ($method->isPublic()) {
            return 'public';
        }
        if ($method->isProtected()) {
            return 'protected';
        }
        return 'private';
    }

    private function getParentMethodAccess(MethodReflection $parentMethod): string
    {
        if ($parentMethod->isPublic()) {
            return 'public';
        }
        if ($parentMethod->isPrivate()) {
            return 'private';
        }
        return 'protected';
    }
}
