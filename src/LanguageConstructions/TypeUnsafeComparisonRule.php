<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Detects type unsafe comparison operations and suggests strict alternatives.
 *
 * This rule identifies comparison operations using '==' or '!=' operators that may
 * lead to unexpected type coercion. It suggests using '===' or '!==' for safer comparisons.
 *
 * The rule targets two main scenarios:
 * - Comparisons involving string literals with non-string operands
 * - Comparisons involving objects that don't support direct comparison
 *
 * @implements Rule<BinaryOp>
 */
final class TypeUnsafeComparisonRule implements Rule
{
    private const array COMPARABLE_CLASSES = [
        '\\Closure',
        '\\DateTime',
        '\\DateTimeImmutable',
        '\\IntlBreakIterator',
        '\\IntlTimeZone',
        '\\PDO',
        '\\PDOStatement',
        '\\ArrayObject',
        '\\SplObjectStorage',
    ];

    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Equal && !$node instanceof NotEqual) {
            return [];
        }

        $targetOperator = $node instanceof Equal ? '===' : '!==';

        // Check for string literal comparisons
        $stringComparisonResult = $this->analyzeStringComparison($node, $scope, $targetOperator);
        if ($stringComparisonResult !== null) {
            return $stringComparisonResult;
        }

        // Check for object comparisons
        $objectComparisonResult = $this->analyzeObjectComparison($node, $scope, $targetOperator);
        if ($objectComparisonResult !== null) {
            return $objectComparisonResult;
        }

        return [];
    }

    /**
     * Analyze comparisons involving string literals
     */
    private function analyzeStringComparison(BinaryOp $node, Scope $scope, string $targetOperator): ?array
    {
        $left = $node->left;
        $right = $node->right;

        $stringLiteral = null;
        $nonStringOperand = null;

        if ($left instanceof String_) {
            $stringLiteral = $left;
            $nonStringOperand = $right;
        } elseif ($right instanceof String_) {
            $stringLiteral = $right;
            $nonStringOperand = $left;
        } else {
            return null; // No string literal involved
        }

        $literalValue = $stringLiteral->value;

        // Skip empty strings or numeric strings
        if ($literalValue === '' || preg_match('/^[+-]?\d*\.?\d+$/', $literalValue) === 1) {
            return null;
        }

        // Check if non-string operand is a class that should implement __toString
        if ($this->shouldImplementToString($nonStringOperand, $scope)) {
            return [
                RuleErrorBuilder::message('Class should implement __toString() method for safe string comparison.')
                    ->identifier('comparison.typeUnsafe')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [
            RuleErrorBuilder::message("Safely use '{$targetOperator}' here.")
                ->identifier('comparison.typeUnsafe')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Analyze comparisons involving objects
     */
    private function analyzeObjectComparison(BinaryOp $node, Scope $scope, string $targetOperator): ?array
    {
        $left = $node->left;
        $right = $node->right;

        $leftType = $scope->getType($left);
        $rightType = $scope->getType($right);

        $leftIsComparable = $this->isComparableObject($leftType);
        $rightIsComparable = $this->isComparableObject($rightType);

        // If neither operand is a comparable object, suggest strict comparison
        if (!$leftIsComparable && !$rightIsComparable) {
            return [
                RuleErrorBuilder::message("Please consider using more strict '{$targetOperator}' here (hidden types casting will not be applied anymore).")
                    ->identifier('comparison.typeUnsafe')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return null;
    }

    /**
     * Check if a type represents a comparable object
     */
    private function isComparableObject(Type $type): bool
    {
        $objectTypes = $type->getObjectTypeOrClassStringObjectType();
        if ($objectTypes === null) {
            return false;
        }

        foreach ($objectTypes->getObjectClassNames() as $className) {
            if (in_array($className, self::COMPARABLE_CLASSES, true)) {
                return true;
            }

            // Check if class implements or extends comparable classes
            if ($this->reflectionProvider->hasClass($className)) {
                $classReflection = $this->reflectionProvider->getClass($className);

                // Check direct class
                if (in_array($classReflection->getName(), self::COMPARABLE_CLASSES, true)) {
                    return true;
                }

                // Check ancestors
                foreach ($classReflection->getAncestors() as $ancestor) {
                    if (in_array($ancestor->getName(), self::COMPARABLE_CLASSES, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if an expression should implement __toString for safe string comparison
     */
    private function shouldImplementToString(Expr $expr, Scope $scope): bool
    {
        $type = $scope->getType($expr);
        $objectTypes = $type->getObjectTypeOrClassStringObjectType();

        if ($objectTypes === null) {
            return false;
        }

        foreach ($objectTypes->getObjectClassNames() as $className) {
            if (!$this->reflectionProvider->hasClass($className)) {
                continue;
            }

            $classReflection = $this->reflectionProvider->getClass($className);

            // Skip if class already implements __toString
            if ($classReflection->hasMethod('__toString')) {
                continue;
            }

            // Check if it's a user-defined class (not built-in)
            if (!$classReflection->isBuiltin()) {
                return true;
            }
        }

        return false;
    }
}