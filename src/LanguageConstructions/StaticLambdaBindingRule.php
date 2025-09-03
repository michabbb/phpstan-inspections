<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Closure>
 */
final class StaticLambdaBindingRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return Closure::class;
    }

    /** @param Closure $node */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->static) {
            return [];
        }

        $errors = [];
        $finder = new NodeFinder();

        $thisUsages = $finder->find($node->stmts ?? [], static fn (Node $n) => $n instanceof Variable && $n->name === 'this');
        foreach ($thisUsages as $thisUsage) {
            $errors[] = RuleErrorBuilder::message('\'this\' can not be used in static closures.')
                ->identifier('closure.staticThisUsage')
                ->line($thisUsage->getStartLine())
                ->build();
        }

        $parentCalls = $finder->find($node->stmts ?? [], static fn (Node $n) =>
            $n instanceof StaticCall &&
            $n->class instanceof Name &&
            $n->class->toString() === 'parent'
        );

        foreach ($parentCalls as $parentCall) {
            if ($this->isNonStaticParentMethod($parentCall, $scope)) {
                $errors[] = RuleErrorBuilder::message('Non-static parent method should not be called in a static closure.')
                    ->identifier('closure.staticParentCall')
                    ->line($parentCall->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    private function isNonStaticParentMethod(StaticCall $node, Scope $scope): bool
    {
        if (!$scope->isInClass()) {
            return false;
        }

        $classReflection = $scope->getClassReflection();
        $parentClasses = $classReflection->getParents();
        if (empty($parentClasses)) {
            return false;
        }
        $parentClass = $parentClasses[0];

        if (!$node->name instanceof Node\Identifier) {
            return false;
        }
        $methodName = $node->name->toString();

        if ($parentClass->hasMethod($methodName)) {
            $methodReflection = $parentClass->getMethod($methodName, $scope);
            return !$methodReflection->isStatic();
        }

        return false;
    }
}
