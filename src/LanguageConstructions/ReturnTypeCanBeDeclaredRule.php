<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPStan\Type\VoidType;

/**
 * Detects methods that can have return type hints declared.
 *
 * This rule identifies methods without explicit return type declarations and suggests
 * appropriate return types based on the method's return statements and expressions.
 *
 * It handles:
 * - void return types when no meaningful return statements exist
 * - Single return types when all returns have the same type
 * - Nullable return types when returns can be null or a specific type
 * - Generator types when yield statements are present
 * - Special handling for abstract methods and magic methods
 *
 * @implements Rule<ClassMethod>
 */
class ReturnTypeCanBeDeclaredRule implements Rule
{
    private const array MAGIC_METHODS = [
        '__construct',
        '__destruct',
        '__call',
        '__callStatic',
        '__get',
        '__set',
        '__isset',
        '__unset',
        '__sleep',
        '__wakeup',
        '__toString',
        '__invoke',
        '__set_state',
        '__clone',
        '__debugInfo',
    ];

    private const array BASIC_TYPES = [
        'array',
        'bool',
        'float',
        'int',
        'string',
    ];

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Skip if not in a class context
        if (!$scope->isInClass()) {
            return [];
        }

        // Skip magic methods
        if (in_array($node->name->toString(), self::MAGIC_METHODS, true)) {
            return [];
        }

        // Skip if method already has return type
        if ($node->returnType !== null) {
            return [];
        }

        // Skip if PHP version doesn't support return types
        if (!$this->supportsReturnTypes($scope)) {
            return [];
        }

        // Handle abstract methods differently
        if ($node->isAbstract()) {
            return $this->processAbstractMethod($node, $scope);
        }

        return $this->processRegularMethod($node, $scope);
    }

    private function supportsReturnTypes(Scope $scope): bool
    {
        // PHP 7.0+ supports return types
        return true; // We'll assume PHP 7.0+ since this is a modern codebase
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function processAbstractMethod(ClassMethod $node, Scope $scope): array
    {
        // For abstract methods, we only suggest if there's a @return annotation
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $docCommentText = $docComment->getText();
        if (!str_contains($docCommentText, '@return')) {
            return [];
        }

        // Parse @return annotation to determine suggested type
        return $this->analyzeReturnTypeFromDocComment($docCommentText, $node, $scope);
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function processRegularMethod(ClassMethod $node, Scope $scope): array
    {
        $returnTypes = $this->collectReturnTypes($node, $scope);

        // Check for yield statements (Generator)
        $hasYield = $this->hasYieldStatement($node);
        if ($hasYield) {
            $returnTypes[] = new ObjectType(\Generator::class);
        }

        // Filter out null types if there are other meaningful types
        $filteredTypes = $this->filterReturnTypes($returnTypes);

        return $this->generateErrors($filteredTypes, $node, $scope);
    }

    /**
     * @return list<Type>
     */
    private function collectReturnTypes(ClassMethod $node, Scope $scope): array
    {
        $returnTypes = [];
        $nodeFinder = new NodeFinder();

        /** @var \PhpParser\Node\Stmt\Return_[] $returnStatements */
        $returnStatements = $nodeFinder->findInstanceOf($node, \PhpParser\Node\Stmt\Return_::class);

        foreach ($returnStatements as $returnStmt) {
            if ($returnStmt->expr === null) {
                // Empty return statement
                continue;
            }

            $returnType = $scope->getType($returnStmt->expr);
            $returnTypes[] = $returnType;
        }

        // If no return statements found, check if method has implicit null return
        if (empty($returnStatements) && !$this->hasUnconditionalReturn($node)) {
            $returnTypes[] = new NullType();
        }

        return $returnTypes;
    }

    private function hasYieldStatement(ClassMethod $node): bool
    {
        $nodeFinder = new NodeFinder();
        return $nodeFinder->findFirstInstanceOf($node, \PhpParser\Node\Expr\Yield_::class) !== null;
    }

    private function hasUnconditionalReturn(ClassMethod $node): bool
    {
        // Simple check: if the last statement is a return, it's unconditional
        $statements = $node->stmts;
        if ($statements === null || empty($statements)) {
            return false;
        }

        $lastStatement = end($statements);
        return $lastStatement instanceof Return_;
    }

    /**
     * @param list<Type> $returnTypes
     * @return list<Type>
     */
    private function filterReturnTypes(array $returnTypes): array
    {
        if (empty($returnTypes)) {
            return [new VoidType()];
        }

        // Remove null types if there are other types
        $nonNullTypes = array_filter($returnTypes, static fn(Type $type) => !$type instanceof NullType);

        if (!empty($nonNullTypes)) {
            return $nonNullTypes;
        }

        return $returnTypes;
    }

    /**
     * @param list<Type> $returnTypes
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function generateErrors(array $returnTypes, ClassMethod $node, Scope $scope): array
    {
        $errors = [];

        if (empty($returnTypes)) {
            return $errors;
        }

        $uniqueTypes = $this->getUniqueTypes($returnTypes);

        if (count($uniqueTypes) === 1) {
            $singleType = $uniqueTypes[0];

            if ($singleType instanceof VoidType) {
                $errors[] = $this->createError('void', $node);
            } elseif ($singleType instanceof NullType) {
                // Skip null-only returns as they're not very useful
            } else {
                $typeString = $this->typeToString($singleType, $scope);
                if ($typeString !== null) {
                    $errors[] = $this->createError($typeString, $node);
                }
            }
        } elseif (count($uniqueTypes) === 2) {
            // Check for nullable type (null + another type)
            $nullType = null;
            $otherType = null;

            foreach ($uniqueTypes as $type) {
                if ($type instanceof NullType) {
                    $nullType = $type;
                } else {
                    $otherType = $type;
                }
            }

            if ($nullType !== null && $otherType !== null) {
                $typeString = $this->typeToString($otherType, $scope);
                if ($typeString !== null) {
                    $errors[] = $this->createError('?' . $typeString, $node);
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<Type> $types
     * @return list<Type>
     */
    private function getUniqueTypes(array $types): array
    {
        $unique = [];
        foreach ($types as $type) {
            $isDuplicate = false;
            foreach ($unique as $existingType) {
                if ($type->equals($existingType)) {
                    $isDuplicate = true;
                    break;
                }
            }
            if (!$isDuplicate) {
                $unique[] = $type;
            }
        }
        return $unique;
    }

    private function typeToString(Type $type, Scope $scope): ?string
    {
        if ($type instanceof ObjectType) {
            $className = $type->getClassName();
            // Try to resolve to short name if imported
            if ($scope->getClassReflection() !== null) {
                $currentClass = $scope->getClassReflection()->getName();
                $namespace = $scope->getNamespace();

                if ($namespace !== null && str_starts_with($className, $namespace . '\\')) {
                    return substr($className, strlen($namespace) + 1);
                }

                // Check if it's the same class (self)
                if ($className === $currentClass) {
                    return 'self';
                }
            }
            return $className;
        }

        if ($type instanceof StringType) {
            return 'string';
        }

        if ($type instanceof ConstantStringType) {
            return 'string';
        }

        // For other types, return null to skip suggestion
        return null;
    }

    private function createError(string $suggestedType, ClassMethod $node): \PHPStan\Rules\IdentifierRuleError
    {
        $methodName = $node->name->toString();

        return RuleErrorBuilder::message(
            sprintf("': %s' can be declared as return type hint", $suggestedType)
        )
            ->identifier('method.returnTypeMissing')
            ->line($node->getStartLine())
            ->build();
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function analyzeReturnTypeFromDocComment(string $docComment, ClassMethod $node, Scope $scope): array
    {
        // Simple parsing of @return annotation
        if (preg_match('/@return\s+([^\s]+)/', $docComment, $matches)) {
            $returnType = trim($matches[1]);

            // Skip complex types for now
            if (!in_array($returnType, self::BASIC_TYPES, true) &&
                !str_starts_with($returnType, '\\') &&
                $returnType !== 'self' &&
                $returnType !== 'static') {
                return [];
            }

            return [$this->createError($returnType, $node)];
        }

        return [];
    }
}