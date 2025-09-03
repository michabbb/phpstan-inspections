<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\Empty_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

/**
 * Suggests alternatives to empty() function for better type safety and clarity.
 *
 * This rule detects usage of the empty() function and suggests more specific alternatives:
 * - For arrays: use count($array) === 0
 * - For nullable values: use $value === null
 * - For better type safety: use explicit type checks instead of empty()
 *
 * The empty() function considers too many values as "empty" which can lead to bugs.
 *
 * @implements Rule<Empty_>
 */
class IsEmptyFunctionUsageRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private bool $reportEmptyUsage = false,
        private bool $suggestCountCheck = false,
        private bool $suggestNullComparison = true,
        private bool $suggestNullComparisonForScalars = true
    ) {
    }

    public function getNodeType(): string
    {
        return Empty_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->expr === null) {
            return [];
        }

        $subject = $node->expr;
        
        // Skip array access expressions (as mentioned in Java code comments)
        if ($subject instanceof Node\Expr\ArrayDimFetch) {
            return [];
        }

        // For now, we'll assume not inverted - proper parent detection would require AST preprocessing
        // Since we can't detect inversion reliably, we'll provide suggestions for the non-inverted case
        $isInverted = false;

        $subjectType = $scope->getType($subject);
        $errors      = [];

        // Case 1: empty(array) - suggest count comparison for countable types
        if ($this->suggestCountCheck && $this->isCountableType($subjectType)) {
            $replacement = sprintf('count(%s) === 0', $this->getNodeText($subject));
            $errors[]    = RuleErrorBuilder::message(
                sprintf('You should probably use \'%s\' instead.', $replacement)
            )
                ->identifier('empty.countComparison')
                ->tip('Use count() comparison for better type safety with arrays and countable objects')
                ->build();
            
            return $errors;
        }

        // Case 2: nullable classes, nullable target core types - suggest null comparison
        if ($this->suggestNullComparison &&
            (($this->suggestNullComparisonForScalars && $this->isNullableCoreType($subjectType)) ||
             $this->isNullableObject($subjectType))
        ) {
            // Skip if subject contains field reference (as per Java logic)
            if (!$this->containsFieldReference($subject)) {
                $replacement = sprintf('%s === null', $this->getNodeText($subject));
                
                $errors[] = RuleErrorBuilder::message(
                    sprintf('You should probably use \'%s\' instead.', $replacement)
                )
                    ->identifier('empty.nullComparison')
                    ->tip('Use explicit null comparison for better type safety')
                    ->build();
                
                return $errors;
            }
        }

        // General case: report empty() usage if configured
        if ($this->reportEmptyUsage) {
            $errors[] = RuleErrorBuilder::message(
                '\'empty(...)\' counts too many values as empty, consider refactoring with type sensitive checks.'
            )
                ->identifier('empty.usage')
                ->tip('Consider using explicit null checks, count() comparisons, or type-specific checks instead')
                ->build();
        }

        return $errors;
    }

    private function isCountableType(Type $type): bool
    {
        // Check for array type using modern PHPStan API
        if ($type->isArray()->yes()) {
            return true;
        }

        // Check for union types containing arrays or countable objects
        if ($type instanceof UnionType) {
            foreach ($type->getTypes() as $innerType) {
                if ($innerType->isArray()->yes()) {
                    return true;
                }
                if ($innerType->isObject()->yes() && $this->implementsCountable($innerType)) {
                    return true;
                }
            }
            return false;
        }

        // Check for objects implementing Countable
        if ($type->isObject()->yes()) {
            return $this->implementsCountable($type);
        }

        return false;
    }

    private function implementsCountable(Type $type): bool
    {
        if (!$type->isObject()->yes()) {
            return false;
        }

        $classNames = $type->getObjectClassNames();
        if (count($classNames) === 0) {
            return false;
        }

        foreach ($classNames as $className) {
            if (!$this->reflectionProvider->hasClass($className)) {
                continue;
            }

            $classReflection = $this->reflectionProvider->getClass($className);
            
            // Check if class implements Countable interface
            foreach ($classReflection->getInterfaces() as $interface) {
                if ($interface->getName() === 'Countable') {
                    return true;
                }
            }

            // Check parent classes
            foreach ($classReflection->getParents() as $parent) {
                foreach ($parent->getInterfaces() as $interface) {
                    if ($interface->getName() === 'Countable') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function isNullableCoreType(Type $type): bool
    {
        if (!$type instanceof UnionType) {
            return false;
        }

        $types = $type->getTypes();
        if (count($types) !== 2) {
            return false;
        }

        $hasNull     = false;
        $hasCoreType = false;

        foreach ($types as $innerType) {
            if ($innerType->isNull()->yes()) {
                $hasNull = true;
            } elseif ($innerType->isInteger()->yes() ||
                     $innerType->isFloat()->yes() ||
                     $innerType->isBoolean()->yes()) {
                $hasCoreType = true;
            }
        }

        return $hasNull && $hasCoreType;
    }

    private function isNullableObject(Type $type): bool
    {
        if (!$type instanceof UnionType) {
            return false;
        }

        $hasNull   = false;
        $hasObject = false;

        foreach ($type->getTypes() as $innerType) {
            if ($innerType->isNull()->yes()) {
                $hasNull = true;
            } elseif ($innerType->isObject()->yes()) {
                $hasObject = true;
            }
        }

        return $hasNull && $hasObject;
    }

    private function containsFieldReference(Node $node): bool
    {
        if ($node instanceof Node\Expr\PropertyFetch || $node instanceof Node\Expr\StaticPropertyFetch) {
            return true;
        }

        // Simple implementation - check common field reference patterns
        if ($node instanceof Node\Expr\MethodCall) {
            return $this->containsFieldReference($node->var);
        }
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            foreach ($node->args as $arg) {
                if ($arg instanceof Node\Arg && $this->containsFieldReference($arg->value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getNodeText(Node $node): string
    {
        // Simple text extraction - in a real implementation, you might want
        // to use the original source code or a more sophisticated printer
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return '$' . $node->name;
        }
        
        if ($node instanceof Node\Expr\PropertyFetch) {
            $propertyName = $node->name;
            if ($propertyName instanceof Node\Identifier) {
                $propertyName = $propertyName->name;
            } else {
                $propertyName = 'property';
            }
            return $this->getNodeText($node->var) . '->' . $propertyName;
        }
        
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            $dimText = '';
            if ($node->dim !== null) {
                $dimText = $this->getNodeText($node->dim);
            }
            return $this->getNodeText($node->var) . '[' . $dimText . ']';
        }

        // Fallback - return a generic placeholder
        return 'expression';
    }
}
