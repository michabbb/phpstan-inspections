<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unnecessary final modifiers on methods.
 *
 * This rule identifies methods declared as final when the final modifier is redundant.
 * A final modifier is unnecessary in the following cases:
 * - Methods in final classes (since the class cannot be extended, final on methods is redundant)
 * - Private methods (since private methods cannot be overridden anyway)
 *
 * The rule helps maintain cleaner code by removing unnecessary modifiers that don't provide
 * any additional protection or behavior.
 *
 * @implements Rule<ClassMethod>
 */
final class UnnecessaryFinalModifierRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        // Only check final methods
        if (!$node->isFinal()) {
            return [];
        }

        // Get the containing class
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $isUnnecessary = false;

        // Check if the class is final
        if ($classReflection->isFinal()) {
            $isUnnecessary = true;
        }

        // Check if the method is private (and doesn't start with "__")
        if ($node->isPrivate()) {
            $methodName = $node->name->toString();
            if (!str_starts_with($methodName, '__')) {
                $isUnnecessary = true;
            }
        }

        if ($isUnnecessary) {
            return [
                RuleErrorBuilder::message('Unnecessary final modifier.')
                    ->identifier('method.unnecessaryFinal')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}