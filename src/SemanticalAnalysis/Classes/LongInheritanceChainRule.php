<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects classes with long inheritance chains and suggests using appropriate design patterns.
 *
 * This rule identifies:
 * - Classes with inheritance chains longer than the configured threshold
 * - Suggests refactoring to composition or other design patterns when inheritance depth becomes excessive
 *
 * @implements Rule<Class_>
 */
class LongInheritanceChainRule implements Rule
{
    private const COMPLAIN_THRESHOLD = 3;

    /** @var array<string> */
    private const SHOW_STOPPERS = [
        '\\PHPUnit_Framework_TestCase',
        '\\PHPUnit\\Framework\\TestCase',
        '\\yii\\base\\Component',
        '\\yii\\base\\Behavior',
        '\\CComponent',
        '\\Zend\\Form\\Form',
        '\\Phalcon\\Di\\Injectable',
    ];

    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $className = $node->name->toString();
        $fqn = $scope->getNamespace() !== null
            ? $scope->getNamespace() . '\\' . $className
            : $className;

        // Skip classes ending with "Exception"
        if (str_ends_with($className, 'Exception')) {
            return [];
        }

        // Skip test classes
        if ($this->isTestContext($scope)) {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($fqn)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($fqn);

        // Skip deprecated classes
        $isDeprecated = $classReflection->isDeprecated();
        if (($isDeprecated !== false) && (is_bool($isDeprecated) ? $isDeprecated : $isDeprecated->yes())) {
            return [];
        }

        $parentsCount = $this->countInheritanceChain($classReflection);

        if ($parentsCount >= self::COMPLAIN_THRESHOLD) {
            return [
                RuleErrorBuilder::message(
                    sprintf('Class has %d parent classes, consider using appropriate design patterns.', $parentsCount)
                )
                ->identifier('inheritance.longChain')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        return [];
    }

    private function isTestContext(Scope $scope): bool
    {
        $namespace = $scope->getNamespace();
        if ($namespace === null) {
            return false;
        }

        return str_contains($namespace, 'Test') || str_contains($namespace, 'Tests');
    }

    private function countInheritanceChain(\PHPStan\Reflection\ClassReflection $classReflection): int
    {
        $count = 0;
        $currentClass = $classReflection;

        // Skip if class is not abstract but extends an abstract class (false positive)
        $parentClass = $currentClass->getParentClass();
        if ($parentClass !== null && !$currentClass->isAbstract() && $parentClass->isAbstract()) {
            return 0;
        }

        while ($parentClass !== null) {
            $count++;

            // Check for show stoppers
            if (in_array($parentClass->getName(), self::SHOW_STOPPERS, true)) {
                $count++;
                break;
            }

            // Stop if parent ends with "Exception"
            if (str_ends_with($parentClass->getName(), 'Exception')) {
                return 0;
            }

            $currentClass = $parentClass;
            $parentClass = $currentClass->getParentClass();
        }

        return $count;
    }
}