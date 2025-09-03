<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

/**
 * @implements Rule<ClassMethod>
 */
class SenselessProxyMethodRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        if ($node->isAbstract() || $node->isPrivate()) {
            return [];
        }

        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection->isInterface() || $classReflection->isTrait()) {
            return [];
        }

        if ($node->stmts === null || count($node->stmts) !== 1) {
            return [];
        }

        $statement = $node->stmts[0];

        if (!$statement instanceof Return_ || !$statement->expr instanceof StaticCall) {
            return [];
        }

        $staticCall = $statement->expr;

        if (!$this->isParentMethodCall($staticCall, $node->name->toString())) {
            return [];
        }

        if (!$this->areParametersUnmodified($staticCall, $node)) {
            return [];
        }

        if (!$this->doSignaturesMatch($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' method can be dropped, as it only calls parent's one.", $node->name->toString())
            )
            ->identifier('method.senselessProxy')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    private function isParentMethodCall(StaticCall $staticCall, string $methodName): bool
    {
        if (!$staticCall->class instanceof Node\Name || $staticCall->class->toString() !== 'parent') {
            return false;
        }

        if (!$staticCall->name instanceof Node\Identifier || $staticCall->name->toString() !== $methodName) {
            return false;
        }

        return true;
    }

    private function areParametersUnmodified(StaticCall $staticCall, ClassMethod $method): bool
    {
        $methodParameters = $method->params;
        $callArgs = $staticCall->args;

        if (count($callArgs) !== count($methodParameters)) {
            return false;
        }

        foreach ($callArgs as $index => $arg) {
            if (!$arg->value instanceof Variable) {
                return false;
            }

            $paramName = $methodParameters[$index]->var->name;
            if ($arg->value->name !== $paramName) {
                return false;
            }
        }

        return true;
    }

    private function doSignaturesMatch(ClassMethod $method, Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();
        $parentClasses = $classReflection->getParents();
        if (empty($parentClasses)) {
            return false;
        }
        $parentClass = $parentClasses[0];

        if (!$parentClass->hasMethod($method->name->toString())) {
            return false;
        }

        $parentMethod = $parentClass->getMethod($method->name->toString(), $scope);
        $currentMethod = $classReflection->getMethod($method->name->toString(), $scope);

        if ($parentMethod->isStatic() !== $currentMethod->isStatic()) {
            return false;
        }

        if ($parentMethod->isFinal() !== $currentMethod->isFinal()) {
            return false;
        }

        if ($parentMethod->isPublic() !== $currentMethod->isPublic() || $parentMethod->isPrivate() !== $currentMethod->isPrivate()) {
            return false;
        }

        $parentParams = $parentMethod->getVariants()[0]->getParameters();
        $currentParams = $currentMethod->getVariants()[0]->getParameters();

        if (count($parentParams) !== count($currentParams)) {
            return false;
        }

        foreach ($parentParams as $index => $parentParam) {
            $currentParam = $currentParams[$index];
            if ($parentParam->getType()->describe(VerbosityLevel::precise()) !== $currentParam->getType()->describe(VerbosityLevel::precise())) {
                return false;
            }
            $parentDefault = $parentParam->getDefaultValue();
            $currentDefault = $currentParam->getDefaultValue();
            if (($parentDefault === null) !== ($currentDefault === null)) {
                return false;
            }
            if ($parentDefault !== null && $currentDefault !== null) {
                if ($parentDefault->describe(VerbosityLevel::precise()) !== $currentDefault->describe(VerbosityLevel::precise())) {
                    return false;
                }
            }
        }

        if ($parentMethod->getVariants()[0]->getReturnType()->describe(VerbosityLevel::precise()) !== $currentMethod->getVariants()[0]->getReturnType()->describe(VerbosityLevel::precise())) {
            return false;
        }

        return true;
    }
}
