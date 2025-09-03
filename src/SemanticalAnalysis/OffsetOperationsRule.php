<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\StringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\UnionType;
use PHPStan\Type\NullType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Constant\ConstantStringType;

/**
 * Rule to detect invalid array and string offset operations.
 *
 * This rule identifies issues with offset operations (array/string access):
 * - Containers that do not support offset operations
 * - Index types that are incompatible with the container's supported index types
 *
 * The rule checks for:
 * - Array access on unsupported types (scalars, objects without offset methods)
 * - String access with incompatible index types
 * - Object access without proper offset implementation
 *
 * @implements Rule<ArrayDimFetch>
 */
class OffsetOperationsRule implements Rule
{
    private const string MESSAGE_NO_OFFSET_SUPPORT = "'%s' may not support offset operations (or its type not annotated properly: %s).";
    private const string MESSAGE_INVALID_INDEX = "Resolved index type (%s) is incompatible with possible %s. Probably just proper type hinting needed.";

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return ArrayDimFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ArrayDimFetch) {
            return [];
        }

        $container = $node->var;
        if ($container === null) {
            return [];
        }

        // Get container type
        $containerType = $scope->getType($container);
        if ($containerType instanceof MixedType) {
            return []; // Skip mixed types
        }

        $allowedIndexTypes = [];
        $supportsOffsets = $this->containerSupportsOffsets($containerType, $allowedIndexTypes, $scope);

        if (!$supportsOffsets && !empty($allowedIndexTypes)) {
            // Container doesn't support offset operations
            $containerText = $this->getExpressionText($container);
            $typesText = implode(', ', $allowedIndexTypes);

            return [
                RuleErrorBuilder::message(
                    sprintf(self::MESSAGE_NO_OFFSET_SUPPORT, $containerText, $typesText)
                )
                ->identifier('offsetOperations.noOffsetSupport')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        // Check index compatibility if we have index and allowed types
        if (!empty($allowedIndexTypes) && $node->dim !== null) {
            $indexType = $scope->getType($node->dim);
            $incompatibleTypes = $this->getIncompatibleIndexTypes($indexType, $allowedIndexTypes);

            if (!empty($incompatibleTypes)) {
                $indexTypesText = implode(', ', $incompatibleTypes);
                $allowedTypesText = implode(', ', $allowedIndexTypes);

                return [
                    RuleErrorBuilder::message(
                        sprintf(self::MESSAGE_INVALID_INDEX, $indexTypesText, $allowedTypesText)
                    )
                    ->identifier('offsetOperations.invalidIndex')
                    ->line($node->getStartLine())
                    ->build(),
                ];
            }
        }

        return [];
    }

    /**
     * Check if the container type supports offset operations
     */
    private function containerSupportsOffsets(\PHPStan\Type\Type $type, array &$allowedIndexTypes, Scope $scope): bool
    {
        // Handle union types
        if ($type instanceof UnionType) {
            $allSupportOffsets = true;
            $collectedIndexTypes = [];

            foreach ($type->getTypes() as $unionType) {
                $tempIndexTypes = [];
                if (!$this->containerSupportsOffsets($unionType, $tempIndexTypes, $scope)) {
                    $allSupportOffsets = false;
                }
                $collectedIndexTypes = array_merge($collectedIndexTypes, $tempIndexTypes);
            }

            $allowedIndexTypes = array_unique($collectedIndexTypes);
            return $allSupportOffsets;
        }

        // Skip null types
        if ($type instanceof NullType) {
            return true;
        }

        // Arrays and strings support offset operations
        if ($type instanceof ArrayType) {
            $allowedIndexTypes[] = 'int';
            $allowedIndexTypes[] = 'string';
            return true;
        }

        if ($type instanceof StringType) {
            $allowedIndexTypes[] = 'int';
            $allowedIndexTypes[] = 'string';
            return true;
        }

        // Objects - check for offset methods
        if ($type instanceof ObjectType) {
            return $this->objectSupportsOffsets($type, $allowedIndexTypes, $scope);
        }

        // Scalars don't support offset operations
        if ($type instanceof IntegerType || $type instanceof FloatType || $type instanceof BooleanType) {
            $allowedIndexTypes[] = get_class($type);
            return false;
        }

        return true; // Unknown types are assumed to support offsets
    }

    /**
     * Check if an object type supports offset operations
     */
    private function objectSupportsOffsets(ObjectType $type, array &$allowedIndexTypes, Scope $scope): bool
    {
        $className = $type->getClassName();

        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $classReflection = $this->reflectionProvider->getClass($className);
        $hasOffsetMethods = false;

        // Check for offset methods
        $offsetMethods = ['offsetGet', 'offsetSet', '__get', '__set'];
        foreach ($offsetMethods as $methodName) {
            if ($classReflection->hasMethod($methodName)) {
                $method = $classReflection->getMethod($methodName, $scope);
                if ($method !== null && $method->isPublic()) {
                    /* For both magic methods and offset methods, allow string and int indexes */
                    $allowedIndexTypes[] = 'string';
                    $allowedIndexTypes[] = 'int';
                    $hasOffsetMethods = true;
                }
            }
        }

        return $hasOffsetMethods;
    }

    /**
     * Collect allowed index types from a parameter type
     */
    private function collectIndexTypesFromType(\PHPStan\Type\Type $type, array &$allowedIndexTypes): void
    {
        if ($type instanceof StringType) {
            $allowedIndexTypes[] = 'string';
        } elseif ($type instanceof IntegerType) {
            $allowedIndexTypes[] = 'int';
        } elseif ($type instanceof UnionType) {
            foreach ($type->getTypes() as $unionType) {
                $this->collectIndexTypesFromType($unionType, $allowedIndexTypes);
            }
        } elseif ($type instanceof MixedType) {
            $allowedIndexTypes[] = 'mixed';
        }
    }

    /**
     * Get incompatible index types from the given type
     */
    private function getIncompatibleIndexTypes(\PHPStan\Type\Type $indexType, array $allowedTypes): array
    {
        $incompatibleTypes = [];

        // Handle mixed type
        if ($indexType instanceof MixedType) {
            return []; // Mixed is always compatible
        }

        // Handle union types
        if ($indexType instanceof UnionType) {
            foreach ($indexType->getTypes() as $unionType) {
                $incompatible = $this->getIncompatibleIndexTypes($unionType, $allowedTypes);
                $incompatibleTypes = array_merge($incompatibleTypes, $incompatible);
            }
            return array_unique($incompatibleTypes);
        }

        // Handle null type
        if ($indexType instanceof NullType) {
            return []; // Null is generally acceptable
        }

        // Check specific types
        $typeName = $this->getTypeName($indexType);
        if ($typeName !== null && !in_array($typeName, $allowedTypes, true) && !in_array('mixed', $allowedTypes, true)) {
            $incompatibleTypes[] = $typeName;
        }

        return $incompatibleTypes;
    }

    /**
     * Get a simple type name for error messages
     */
    private function getTypeName(\PHPStan\Type\Type $type): ?string
    {
        if ($type instanceof StringType) {
            return 'string';
        }
        if ($type instanceof IntegerType) {
            return 'int';
        }
        if ($type instanceof BooleanType) {
            return 'bool';
        }
        if ($type instanceof FloatType) {
            return 'float';
        }
        if ($type instanceof NullType) {
            return 'null';
        }
        if ($type instanceof MixedType) {
            return 'mixed';
        }

        return null;
    }

    /**
     * Get a textual representation of an expression for error messages
     */
    private function getExpressionText(Node\Expr $expr): string
    {
        if ($expr instanceof Variable) {
            return '$' . $expr->name;
        }

        if ($expr instanceof PropertyFetch) {
            $varText = $this->getExpressionText($expr->var);
            $propertyName = $expr->name instanceof Node\Identifier ? $expr->name->name : 'property';
            return $varText . '->' . $propertyName;
        }

        if ($expr instanceof StaticPropertyFetch) {
            $classText = $expr->class instanceof Name ? $expr->class->toString() : 'class';
            $propertyName = $expr->name instanceof Node\Identifier ? $expr->name->name : 'property';
            return $classText . '::$' . $propertyName;
        }

        if ($expr instanceof MethodCall) {
            $varText = $this->getExpressionText($expr->var);
            $methodName = $expr->name instanceof Node\Identifier ? $expr->name->name : 'method';
            return $varText . '->' . $methodName . '()';
        }

        if ($expr instanceof StaticCall) {
            $classText = $expr->class instanceof Name ? $expr->class->toString() : 'class';
            $methodName = $expr->name instanceof Node\Identifier ? $expr->name->name : 'method';
            return $classText . '::' . $methodName . '()';
        }

        if ($expr instanceof FuncCall) {
            $functionName = $expr->name instanceof Name ? $expr->name->toString() : 'function';
            return $functionName . '()';
        }

        return 'expression';
    }
}