<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of class name instead of 'self' within class methods and suggests replacement.
 *
 * This rule identifies patterns where the current class name is used instead of the 'self' keyword:
 * - new MyClass() → new self()
 * - MyClass::CONSTANT → self::CONSTANT
 * - MyClass::staticMethod() → self::staticMethod()
 * - MyClass::$staticProperty → self::$staticProperty
 * - MyClass::class → __CLASS__
 *
 * The rule only applies within class methods (not traits, not anonymous classes, not abstract methods)
 * and excludes references in class inheritance declarations.
 *
 * @implements Rule<ClassMethod>
 */
final class SelfClassReferencingRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {

        // Skip abstract methods
        if ($node->isAbstract()) {
            return [];
        }

        // Check if we're in a class context
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection->isAnonymous()) {
            return [];
        }

        $className = $classReflection->getName();
        $errors = [];

        // Find all relevant nodes in the method body
        $nodeFinder = new NodeFinder();
        $stmts = $node->stmts ?? [];

        // Find new expressions
        $newExpressions = $nodeFinder->findInstanceOf($stmts, New_::class);
        foreach ($newExpressions as $newExpr) {
            if ($newExpr->class instanceof Name && $newExpr->class->toString() === $className) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf("Class reference '%s' could be replaced by 'self'", $newExpr->class->toString())
                )
                    ->identifier('classReference.selfReferencing')
                    ->line($newExpr->getStartLine())
                    ->build();
            }
        }

        // Find static calls
        $staticCalls = $nodeFinder->findInstanceOf($stmts, StaticCall::class);
        foreach ($staticCalls as $staticCall) {
            if ($staticCall->class instanceof Name && $staticCall->class->toString() === $className) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf("Class reference '%s' could be replaced by 'self'", $staticCall->class->toString())
                )
                    ->identifier('classReference.selfReferencing')
                    ->line($staticCall->getStartLine())
                    ->build();
            }
        }

        // Find static property fetches
        $staticPropertyFetches = $nodeFinder->findInstanceOf($stmts, StaticPropertyFetch::class);
        foreach ($staticPropertyFetches as $staticPropertyFetch) {
            if ($staticPropertyFetch->class instanceof Name && $staticPropertyFetch->class->toString() === $className) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf("Class reference '%s' could be replaced by 'self'", $staticPropertyFetch->class->toString())
                )
                    ->identifier('classReference.selfReferencing')
                    ->line($staticPropertyFetch->getStartLine())
                    ->build();
            }
        }

        // Find class constant fetches
        $classConstFetches = $nodeFinder->findInstanceOf($stmts, ClassConstFetch::class);
        foreach ($classConstFetches as $classConstFetch) {
            if ($classConstFetch->class instanceof Name && $classConstFetch->class->toString() === $className) {
                // Special case: MyClass::class should become __CLASS__
                if ($classConstFetch->name instanceof Node\Identifier && $classConstFetch->name->toString() === 'class') {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf("Class reference '%s::class' could be replaced by '__CLASS__'", $className)
                    )
                        ->identifier('classReference.selfReferencing')
                        ->line($classConstFetch->getStartLine())
                        ->build();
                } else {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf("Class reference '%s' could be replaced by 'self'", $classConstFetch->class->toString())
                    )
                        ->identifier('classReference.selfReferencing')
                        ->line($classConstFetch->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }
}