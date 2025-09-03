<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Array_ as CastArray;
use PhpParser\Node\Expr\Cast\Bool_ as CastBool;
use PhpParser\Node\Expr\Cast\Double as CastDouble;
use PhpParser\Node\Expr\Cast\Int_ as CastInt;
use PhpParser\Node\Expr\Cast\String_ as CastString;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\AssignOp\Concat as AssignConcat;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;

/**
 * Detects unnecessary type casting.
 *
 * This rule identifies type casting operations that are redundant because:
 * - The argument is already of the target type
 * - String casting in concatenation context (automatic conversion)
 * - String casting in self-assignment concatenation (.=)
 *
 * @implements Rule<Node>
 */
final class UnnecessaryCastingRule implements Rule
{
    private const string MESSAGE_GENERIC = 'This type casting is not necessary, as the argument is of needed type.';
    private const string MESSAGE_CONCATENATE = 'This type casting is not necessary, as concatenation casts the argument.';

    /** @var array<class-string<Cast>, string> */
    private array $castTypeMapping = [
        CastInt::class => 'int',
        CastDouble::class => 'float',
        CastBool::class => 'bool',
        CastString::class => 'string',
        CastArray::class => 'array',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Cast) {
            return [];
        }

        // Check for string casting in concatenation context
        if ($node instanceof CastString) {
            if ($this->isInConcatenationContext($node)) {
                return [
                    RuleErrorBuilder::message(self::MESSAGE_CONCATENATE)
                        ->identifier('casting.unnecessary')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        // Check for unnecessary casting due to type compatibility
        if (isset($this->castTypeMapping[$node::class])) {
            $targetType = $this->castTypeMapping[$node::class];
            $argumentType = $scope->getType($node->expr);

            if ($this->isUnnecessaryCast($argumentType, $targetType, $node, $scope)) {
                return [
                    RuleErrorBuilder::message(self::MESSAGE_GENERIC)
                        ->identifier('casting.unnecessary')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function isInConcatenationContext(CastString $node): bool
    {
        $parent = $node->getAttribute('parent');

        // Check for concatenation: ... . (string)...
        if ($parent instanceof Concat) {
            return true;
        }

        // Check for self-assignment concatenation: ... .= (string)...
        if ($parent instanceof AssignConcat) {
            return true;
        }

        return false;
    }

    private function isUnnecessaryCast(\PHPStan\Type\Type $argumentType, string $targetType, Cast $node, Scope $scope): bool
    {
        // Skip if it's a variable from a weakly typed parameter
        if ($node->expr instanceof Variable && $this->isWeakTypedParameter($node->expr, $scope)) {
            return false;
        }

        // Skip if it's a property fetch (object properties) - matching original Java behavior
        // Original Java only checks private fields strictly, we skip all property fetches
        if ($node->expr instanceof PropertyFetch) {
            return false;
        }

        // Skip if it's part of a null coalescing operation
        if ($this->isNullCoalescingOnly($node)) {
            return false;
        }

        // Check if the argument type matches the target type
        $expectedType = $this->getExpectedType($targetType);

        if ($expectedType === null) {
            return false;
        }

        // For union types, check if all types are compatible
        if ($argumentType instanceof UnionType) {
            $types = $argumentType->getTypes();
            if (count($types) !== 1) {
                return false;
            }
            $argumentType = $types[0];
        }

        return $expectedType->isSuperTypeOf($argumentType)->yes();
    }

    private function getExpectedType(string $targetType): ?\PHPStan\Type\Type
    {
        return match ($targetType) {
            'int' => new IntegerType(),
            'float' => new FloatType(),
            'bool' => new BooleanType(),
            'string' => new StringType(),
            'array' => new ArrayType(new \PHPStan\Type\MixedType(), new \PHPStan\Type\MixedType()),
            default => null,
        };
    }

    private function isWeakTypedParameter(Variable $variable, Scope $scope): bool
    {
        $function = $scope->getFunction();
        if ($function === null) {
            return false;
        }

        $parameters = $function->getParameters();
        $variableName = $variable->name;

        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $variableName) {
                // Check if parameter has no type hint (weak typing)
                return $parameter->getType() instanceof \PHPStan\Type\MixedType;
            }
        }

        return false;
    }

    private function isNullCoalescingOnly(Cast $node): bool
    {
        $parent = $node->getAttribute('parent');

        // Walk up the tree to find if this is part of a null coalescing operation
        while ($parent !== null) {
            if ($parent instanceof Coalesce) {
                return true;
            }

            // Stop at certain boundaries
            if ($parent instanceof Node\Stmt) {
                break;
            }

            $parent = $parent->getAttribute('parent');
        }

        return false;
    }

}
