<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
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
use PHPStan\Type\VerbosityLevel;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Detects improper usage of the null-coalescing operator (??).
 *
 * This rule identifies:
 * - Redundant 'call() ?? null' patterns that can be simplified to just 'call()'
 * - Type mismatches between left and right operands where they should be complementary
 *
 * The rule helps reduce cognitive load by suggesting cleaner alternatives and
 * ensures type safety by detecting non-complementary operand types.
 *
 * @implements Rule<Coalesce>
 */
class ProperNullCoalescingOperatorUsageRule implements Rule
{
    private const string MESSAGE_SIMPLIFY = "It possible to use '%s' instead (reduces cognitive load).";
    private const string MESSAGE_MISMATCH = "Resolved operands types are not complimentary, while they should be (%s vs %s).";

    /** @var array<int, bool> */
    private array $coalesceInCastContext = [];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return Coalesce::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Coalesce) {
            return [];
        }


        // Skip if this coalesce is part of another coalesce
        if ($this->isPartOfCoalesce($node)) {
            return [];
        }

        // Skip if this coalesce is type casted (heuristic detection)
        if ($this->isTypeCasted($node, $scope)) {
            return [];
        }

        $left = $node->left;
        $right = $node->right;

        $errors = [];

        // Case 1: `call() ?? null` - suggest simplification
        if ($this->isNull($right, $scope) && $this->isCallableExpression($left)) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(self::MESSAGE_SIMPLIFY, $this->getExpressionText($left))
            )
            ->identifier('nullCoalescing.redundant')
            ->line($node->getStartLine())
            ->build();
        }

        // Case 2: Type complementarity check
        $leftType = $scope->getType($left);
        $rightType = $scope->getType($right);

        if ($this->shouldAnalyzeTypes($leftType, $rightType)) {
            $complimentary = $this->areTypesComplimentary($leftType, $rightType);

            if (!$complimentary) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        self::MESSAGE_MISMATCH,
                        $leftType->describe(\PHPStan\Type\VerbosityLevel::typeOnly()),
                        $rightType->describe(\PHPStan\Type\VerbosityLevel::typeOnly())
                    )
                )
                ->identifier('nullCoalescing.typeMismatch')
                ->line($node->getStartLine())
                ->build();
            }
        }

        return $errors;
    }

    private function isPartOfCoalesce(Coalesce $node): bool
    {
        $parent = $node->getAttribute('parent');
        return $parent instanceof Coalesce;
    }

    private function isTypeCasted(Coalesce $node, Scope $scope): bool
    {
        // Prefer a structural check first: detect actual cast parent if present
        if ($this->findCastParent($node, $scope)) {
            return true;
        }

        // Fallback: limited heuristics for common safe patterns
        return $this->isLikelyInCastContext($node, $scope);
    }
    
    /**
     * Check if this coalesce is likely in a context where casting resolves type mismatches
     */
    private function isLikelyInCastContext(Coalesce $node, Scope $scope): bool
    {
        // For the specific case mentioned by the user:
        // $expectedSize = (int)($response->header('Content-Length') ?? 0);
        // 
        // The pattern is: Assignment where the right side is cast-wrapped coalesce
        
        // Check if both operands, when cast to the same type, would be compatible
        $left = $node->left;
        $right = $node->right;
        $leftType = $scope->getType($left);
        
        // More specific heuristic: Only skip type checking if this looks like a 
        // header/response pattern that's commonly cast to numeric with numeric fallback
        if ($this->isHeaderOrResponsePattern($left) && $this->isNumericLiteral($right)) {
            return true;
        }
        
        // NOTE: Do NOT indiscriminately skip type checks on generic
        // numeric/string/bool fallbacks — that would hide true mismatches
        // like `getString() ?? 42`. Broader heuristics are only safe when
        // an actual cast context is structurally detected above.
        
        // Don't be too broad - only specific patterns should be exempt
        return false;
    }
    
    private function isNumericLiteral(Node $node): bool
    {
        return $node instanceof Node\Scalar\LNumber || 
               $node instanceof Node\Scalar\DNumber ||
               ($node instanceof Node\Scalar\String_ && is_numeric($node->value));
    }
    
    private function isStringLiteral(Node $node): bool
    {
        return $node instanceof Node\Scalar\String_;
    }
    
    private function isBooleanLiteral(Node $node): bool
    {
        return $node instanceof Node\Expr\ConstFetch
            && $node->name instanceof Node\Name
            && in_array(strtolower($node->name->toString()), ['true', 'false'], true);
    }
    
    private function isPotentiallyCastableToNumeric(Type $type): bool
    {
        if ($type->isInteger()->yes() || $type->isFloat()->yes()) {
            return true;
        }
        // Numeric strings are commonly cast
        if ($type->isString()->yes()) {
            return true;
        }
        // Booleans are castable to int/float in PHP
        if ($type->isBoolean()->yes()) {
            return true;
        }
        return false;
    }
    
    private function isPotentiallyCastableToString(Type $type): bool
    {
        if ($type->isString()->yes() || $type->isInteger()->yes() || $type->isFloat()->yes() || $type->isBoolean()->yes()) {
            return true;
        }
        // Objects with __toString
        if ($type instanceof ObjectType) {
            $className = $type->getClassName();
            if ($this->reflectionProvider->hasClass($className) && $this->reflectionProvider->getClass($className)->hasMethod('__toString')) {
                return true;
            }
        }
        return false;
    }
    
    private function isPotentiallyCastableToBool(Type $type): bool
    {
        // In PHP, most types are castable to bool; keep it conservative
        if ($type->isBoolean()->yes() || $type->isString()->yes() || $type->isInteger()->yes() || $type->isFloat()->yes()) {
            return true;
        }
        return false;
    }
    
    private function isHeaderOrResponsePattern(Node $node): bool
    {
        // Detect patterns like $response->header('...') which are commonly cast
        if ($node instanceof MethodCall && 
            $node->name instanceof Node\Identifier && 
            in_array(strtolower($node->name->toString()), ['header', 'getheader'], true)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Find if this Coalesce node is inside a Cast by checking the file structure
     */
    private function findCastParent(Coalesce $node, Scope $scope): bool
    {
        $parent = $node->getAttribute('parent');
        if ($parent === null) {
            // Try a source-based fallback when parent attributes are unavailable
            return $this->isCastContextBySource($node, $scope);
        }

        // Direct cast: (cast)($coalesce)
        if ($parent instanceof Cast) {
            return true;
        }

        // Parenthesized cast: (cast)((... ?? ...))
        if ($parent instanceof Node\Expr\Paren) {
            $grandParent = $parent->getAttribute('parent');
            if ($grandParent instanceof Cast) {
                return true;
            }
        }

        // Walk up a few levels for nested structures just in case
        $current = $parent;
        for ($i = 0; $i < 3; $i++) {
            $current = $current->getAttribute('parent');
            if ($current === null) {
                // If parent attributes are not connected, try source-based fallback
                return $this->isCastContextBySource($node, $scope);
            }
            if ($current instanceof Cast) {
                return true;
            }
        }

        // Final fallback on failure to confirm via parent chain
        return $this->isCastContextBySource($node, $scope);
    }

    /**
     * Fallback: detect a cast context by inspecting the original source around the expression.
     *
     * Targets patterns like: (int)(<coalesce>) or (float) ( ( <coalesce> ) )
     */
    private function isCastContextBySource(Coalesce $node, Scope $scope): bool
    {
        // We rely on file positions provided by PHP-Parser. If not present, bail out.
        $startPos = $node->getAttribute('startFilePos');
        if (!is_int($startPos)) {
            return false;
        }

        // Best effort to read current file content. If unavailable, skip.
        $fileName = $scope->getFile();

        $source = @file_get_contents($fileName);
        if ($source === false) {
            return false;
        }

        // Look behind the coalesce start for a typical cast pattern "(type)(" with optional spaces
        $lookBehind = 120;
        $sliceStart = $startPos - $lookBehind;
        if ($sliceStart < 0) {
            $sliceStart = 0;
        }
        $prefix = substr($source, $sliceStart, $startPos - $sliceStart);
        if ($prefix === '') {
            return false;
        }

        // Normalize whitespace at the end; we only care about tail context
        $prefix = rtrim($prefix);

        // We're trying to find pattern: (int|float|double|string|bool|boolean|array|object)\s*\(
        // anchored at the end of the prefix (immediately before the coalesce expression which
        // commonly starts with a parenthesis in these cast-wrapped cases).
        $castPattern = '/\((?:int|integer|float|double|string|bool|boolean|array|object)\)\s*\(\s*$/i';
        if (preg_match($castPattern, $prefix) === 1) {
            return true;
        }

        // Also support a variant where there may be an extra parenthesis before the coalesce
        // e.g. (int) ( ( <coalesce> ) )
        $castPatternLoose = '/\((?:int|integer|float|double|string|bool|boolean|array|object)\)\s*\(\s*\(\s*$/i';
        return preg_match($castPatternLoose, $prefix) === 1;
    }

    private function isNull(Node $node, Scope $scope): bool
    {
        $type = $scope->getType($node);
        return $type instanceof NullType;
    }

    private function isCallableExpression(Node $node): bool
    {
        return $node instanceof FuncCall
            || $node instanceof MethodCall
            || $node instanceof NullsafeMethodCall
            || $node instanceof StaticCall
            || $node instanceof PropertyFetch
            || $node instanceof NullsafePropertyFetch;
    }

    private function getExpressionText(Node $node): string
    {
        // For simple reconstruction of the expression text
        if ($node instanceof Variable && is_string($node->name)) {
            return '$' . $node->name;
        }

        if ($node instanceof FuncCall && $node->name instanceof Node\Name) {
            return $node->name->toString() . '()';
        }

        if ($node instanceof MethodCall && $node->name instanceof Node\Identifier) {
            $varName = $node->var instanceof Variable && is_string($node->var->name)
                ? '$' . $node->var->name
                : 'object';
            return $varName . '->' . $node->name->toString() . '()';
        }

        if ($node instanceof StaticCall && $node->name instanceof Node\Identifier) {
            $className = $node->class instanceof Node\Name
                ? $node->class->toString()
                : 'Class';
            return $className . '::' . $node->name->toString() . '()';
        }

        // Fallback to a generic representation
        return 'expression';
    }

    private function shouldAnalyzeTypes(Type $leftType, Type $rightType): bool
    {
        // Skip analysis if either type is mixed or unknown
        if ($leftType instanceof MixedType || $rightType instanceof MixedType) {
            return false;
        }

        // Skip if types contain null (already handled by null coalescing)
        if ($leftType->isSuperTypeOf(new NullType())->yes() ||
            $rightType->isSuperTypeOf(new NullType())->yes()) {
            return false;
        }

        return true;
    }

    private function areTypesComplimentary(Type $leftType, Type $rightType): bool
    {
        // Check if right type contains left type (overlapping)
        if ($rightType->isSuperTypeOf($leftType)->yes()) {
            return true;
        }

        // Check if left type contains right type
        if ($leftType->isSuperTypeOf($rightType)->yes()) {
            return true;
        }

        // Check for related class types
        return $this->areRelatedClassTypes($leftType, $rightType);
    }

    private function areRelatedClassTypes(Type $leftType, Type $rightType): bool
    {
        $leftClasses = $this->extractClassTypes($leftType);
        $rightClasses = $this->extractClassTypes($rightType);

        if (empty($leftClasses) || empty($rightClasses)) {
            return false;
        }

        // Check if any left class is related to any right class
        foreach ($leftClasses as $leftClass) {
            foreach ($rightClasses as $rightClass) {
                if ($this->areClassesRelated($leftClass, $rightClass)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function extractClassTypes(Type $type): array
    {
        $classes = [];

        if ($type instanceof ObjectType) {
            $classes[] = $type->getClassName();
        } elseif ($type instanceof UnionType) {
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof ObjectType) {
                    $classes[] = $subType->getClassName();
                }
            }
        }

        return $classes;
    }

    private function areClassesRelated(string $leftClass, string $rightClass): bool
    {
        if (!$this->reflectionProvider->hasClass($leftClass) ||
            !$this->reflectionProvider->hasClass($rightClass)) {
            return false;
        }

        $leftReflection = $this->reflectionProvider->getClass($leftClass);
        $rightReflection = $this->reflectionProvider->getClass($rightClass);

        // Check if one extends the other
        if ($leftReflection->isSubclassOf($rightClass) || $rightReflection->isSubclassOf($leftClass)) {
            return true;
        }

        // Check if they implement the same interface
        $leftInterfaces = $leftReflection->getInterfaces();
        $rightInterfaces = $rightReflection->getInterfaces();

        foreach ($leftInterfaces as $leftInterface) {
            foreach ($rightInterfaces as $rightInterface) {
                if ($leftInterface->getName() === $rightInterface->getName()) {
                    return true;
                }
            }
        }

        return false;
    }
}
