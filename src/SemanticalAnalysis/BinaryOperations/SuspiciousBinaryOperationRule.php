<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\BinaryOperations;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\BinaryOp\SmallerOrEqual;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\UnaryOp\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\NullType;
use PHPStan\Type\UnionType;

/**
 * Detects suspicious binary operations that may indicate bugs or unclear code.
 *
 * This rule identifies various patterns of potentially problematic binary operations:
 * - Identical operands in comparisons (e.g., $a == $a)
 * - Misplaced operators in function calls
 * - instanceof checks against traits (always returns false)
 * - Concatenation with arrays
 * - Null coalescing operator precedence issues
 * - Hardcoded constants in logical operations
 * - Unclear operator precedence
 * - Incorrect operators in assignments or arrays
 * - Comparisons with nullable arguments
 *
 * @implements Rule<BinaryOp>
 */
final class SuspiciousBinaryOperationRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private bool $verifyConstantsInConditions = true,
        private bool $verifyUnclearOperationsPriorities = true
    ) {}

    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof BinaryOp) {
            return [];
        }

        $errors = [];

        // Apply all strategies
        $errors = array_merge($errors, $this->checkIdenticalOperands($node, $scope));
        $errors = array_merge($errors, $this->checkMisplacedOperator($node, $scope));
        $errors = array_merge($errors, $this->checkInstanceOfTrait($node, $scope));
        $errors = array_merge($errors, $this->checkConcatenationWithArray($node, $scope));
        $errors = array_merge($errors, $this->checkNullCoalescingCorrectness($node, $scope));

        if ($this->verifyConstantsInConditions) {
            $errors = array_merge($errors, $this->checkHardcodedConstants($node, $scope));
        }

        if ($this->verifyUnclearOperationsPriorities) {
            $errors = array_merge($errors, $this->checkUnclearOperationsPriority($node, $scope));
        }

        $errors = array_merge($errors, $this->checkEqualsInAssignmentContext($node, $scope));
        $errors = array_merge($errors, $this->checkGreaterOrEqualInHashElement($node, $scope));
        $errors = array_merge($errors, $this->checkNullableArgumentComparison($node, $scope));

        return $errors;
    }

    /**
     * Check for identical operands in comparisons
     */
    private function checkIdenticalOperands(BinaryOp $node, Scope $scope): array
    {
        $comparisonOps = [
            Equal::class,
            Identical::class,
            NotEqual::class,
            NotIdentical::class,
            Greater::class,
            GreaterOrEqual::class,
            Smaller::class,
            SmallerOrEqual::class,
            Instanceof_::class,
        ];

        if (!in_array($node::class, $comparisonOps, true)) {
            return [];
        }

        // Check if operands are structurally equivalent
        if ($this->areOperandsEquivalent($node->left, $node->right, $scope)) {
            return [
                RuleErrorBuilder::message('Left and right operands are identical.')
                    ->identifier('binaryOperation.identicalOperands')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Check for misplaced operators in function calls
     */
    private function checkMisplacedOperator(BinaryOp $node, Scope $scope): array
    {
        $comparisonOps = [
            Equal::class,
            NotEqual::class,
            Identical::class,
            NotIdentical::class,
            Greater::class,
            GreaterOrEqual::class,
            Smaller::class,
            SmallerOrEqual::class,
        ];

        if (!in_array($node::class, $comparisonOps, true)) {
            return [];
        }

        // Check if this binary operation is the last argument in a function call
        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Arg) {
            $argsParent = $parent->getAttribute('parent');
            if ($argsParent instanceof Node\Expr\FuncCall || $argsParent instanceof Node\Expr\MethodCall || $argsParent instanceof Node\Expr\StaticCall) {
                $args = $argsParent instanceof Node\Expr\FuncCall ? $argsParent->args : ($argsParent instanceof Node\Expr\MethodCall ? $argsParent->args : $argsParent->args);
                if (count($args) > 0 && end($args) === $parent) {
                    return [
                        RuleErrorBuilder::message('This operator is probably misplaced.')
                            ->identifier('binaryOperation.misplacedOperator')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }

    /**
     * Check for instanceof against traits
     */
    private function checkInstanceOfTrait(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof Instanceof_) {
            return [];
        }

        if ($node->class instanceof Name) {
            $className = $node->class->toString();
            if ($this->reflectionProvider->hasClass($className)) {
                $classReflection = $this->reflectionProvider->getClass($className);
                if ($classReflection->isTrait()) {
                    return [
                        RuleErrorBuilder::message('instanceof against traits returns \'false\'.')
                            ->identifier('binaryOperation.instanceofTrait')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }
        }

        return [];
    }

    /**
     * Check for concatenation with arrays
     */
    private function checkConcatenationWithArray(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof Concat) {
            return [];
        }

        $leftType = $scope->getType($node->left);
        $rightType = $scope->getType($node->right);

        if ($leftType->isArray()->yes() || $rightType->isArray()->yes()) {
            return [
                RuleErrorBuilder::message('Concatenation with an array doesn\'t make much sense here.')
                    ->identifier('binaryOperation.concatenationWithArray')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Check for null coalescing operator precedence issues
     */
    private function checkNullCoalescingCorrectness(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof Coalesce) {
            return [];
        }

        if ($node->left instanceof BooleanNot || $node->left instanceof Cast) {
            return [
                RuleErrorBuilder::message('The operation results to \'' . $this->getNodeText($node->left) . '\', please add missing parentheses.')
                    ->identifier('binaryOperation.nullCoalescingPrecedence')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Check for hardcoded constants in logical operations
     */
    private function checkHardcodedConstants(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof LogicalAnd && !$node instanceof LogicalOr) {
            return [];
        }

        $errors = [];

        // Check left operand
        if ($this->isHardcodedConstant($node->left, $scope)) {
            $message = $this->getHardcodedConstantMessage($node->left, $node instanceof LogicalAnd, $scope);
            if ($message !== null) {
                $errors[] = RuleErrorBuilder::message($message)
                    ->identifier('binaryOperation.hardcodedConstant')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        // Check right operand
        if ($this->isHardcodedConstant($node->right, $scope)) {
            $message = $this->getHardcodedConstantMessage($node->right, $node instanceof LogicalAnd, $scope);
            if ($message !== null) {
                $errors[] = RuleErrorBuilder::message($message)
                    ->identifier('binaryOperation.hardcodedConstant')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * Check for unclear operations priority
     */
    private function checkUnclearOperationsPriority(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof LogicalAnd && !$node instanceof LogicalOr) {
            return [];
        }

        $parent = $node->getAttribute('parent');
        if ($parent instanceof BinaryOp && ($parent instanceof LogicalAnd || $parent instanceof LogicalOr)) {
            if ($parent::class !== $node::class) {
                return [
                    RuleErrorBuilder::message('Operations priority might differ from what you expect: please wrap needed with \'(...)\'.')
                        ->identifier('binaryOperation.unclearPriority')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    /**
     * Check for == in assignment context
     */
    private function checkEqualsInAssignmentContext(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof Equal) {
            return [];
        }

        $parent = $node->getAttribute('parent');
        if ($parent instanceof Expression) {
            return [
                RuleErrorBuilder::message('It seems that \'=\' should be here.')
                    ->identifier('binaryOperation.equalsInAssignment')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Check for >= in hash element context
     */
    private function checkGreaterOrEqualInHashElement(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof GreaterOrEqual) {
            return [];
        }

        if (!$node->left instanceof String_) {
            return [];
        }

        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Expr\ArrayItem) {
            $arrayParent = $parent->getAttribute('parent');
            if ($arrayParent instanceof Node\Expr\Array_) {
                return [
                    RuleErrorBuilder::message('It seems that \'=>\' should be here.')
                        ->identifier('binaryOperation.greaterEqualInArray')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    /**
     * Check for nullable argument comparisons
     */
    private function checkNullableArgumentComparison(BinaryOp $node, Scope $scope): array
    {
        if (!$node instanceof Smaller && !$node instanceof SmallerOrEqual) {
            return [];
        }

        $parent = $node->getAttribute('parent');
        if ($parent instanceof BooleanNot) {
            $leftType = $scope->getType($node->left);
            if ($leftType instanceof UnionType && $leftType->isSuperTypeOf(new NullType())->yes()) {
                $replacement = $node instanceof Smaller ? '>=' : '>';
                return [
                    RuleErrorBuilder::message('This might work not as expected (an argument can be null/false), use \'' . $this->getNodeText($node->left) . ' ' . $replacement . ' ' . $this->getNodeText($node->right) . '\' to be sure.')
                        ->identifier('binaryOperation.nullableArgumentComparison')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function areOperandsEquivalent(Node $left, Node $right, Scope $scope): bool
    {
        // First check if they are the same class type
        if ($left::class !== $right::class) {
            return false;
        }
        
        // Use proper structural equivalence check
        return $this->areNodesStructurallyEquivalent($left, $right);
    }

    private function isHardcodedConstant(Node $node, Scope $scope): bool
    {
        $type = $scope->getType($node);
        return $type instanceof ConstantBooleanType || $type instanceof NullType;
    }

    private function getHardcodedConstantMessage(Node $node, bool $isAndOperation, Scope $scope): ?string
    {
        $type = $scope->getType($node);

        if ($type instanceof ConstantBooleanType) {
            $value = $type->getValue();
            if ($isAndOperation) {
                return $value ? 'This operand doesn\'t make any sense here.' : 'This operand enforces the operation result.';
            } else {
                return $value ? 'This operand enforces the operation result.' : 'This operand doesn\'t make any sense here.';
            }
        }

        if ($type instanceof NullType) {
            if ($isAndOperation) {
                return 'This operand enforces the operation result.';
            } else {
                return 'This operand doesn\'t make any sense here.';
            }
        }

        return null;
    }

    private function areNodesStructurallyEquivalent(Node $left, Node $right): bool
    {
        // Handle variables specially (like the Java original)
        if ($left instanceof Node\Expr\Variable && $right instanceof Node\Expr\Variable) {
            return $left->name === $right->name;
        }
        
        // Handle property fetch
        if ($left instanceof Node\Expr\PropertyFetch && $right instanceof Node\Expr\PropertyFetch) {
            return $this->areNodesStructurallyEquivalent($left->var, $right->var) &&
                   $this->areNodesStructurallyEquivalent($left->name, $right->name);
        }
        
        // Handle method calls
        if ($left instanceof Node\Expr\MethodCall && $right instanceof Node\Expr\MethodCall) {
            return $this->areNodesStructurallyEquivalent($left->var, $right->var) &&
                   $this->areNodesStructurallyEquivalent($left->name, $right->name) &&
                   $this->areArgumentsEquivalent($left->args, $right->args);
        }
        
        // Handle array access
        if ($left instanceof Node\Expr\ArrayDimFetch && $right instanceof Node\Expr\ArrayDimFetch) {
            return $this->areNodesStructurallyEquivalent($left->var, $right->var) &&
                   (($left->dim === null && $right->dim === null) ||
                    ($left->dim !== null && $right->dim !== null && $this->areNodesStructurallyEquivalent($left->dim, $right->dim)));
        }
        
        // Handle identifiers
        if ($left instanceof Node\Identifier && $right instanceof Node\Identifier) {
            return $left->name === $right->name;
        }
        
        // Handle scalars
        if ($left instanceof String_ && $right instanceof String_) {
            return $left->value === $right->value;
        }
        
        if ($left instanceof Node\Scalar\LNumber && $right instanceof Node\Scalar\LNumber) {
            return $left->value === $right->value;
        }
        
        if ($left instanceof Node\Scalar\DNumber && $right instanceof Node\Scalar\DNumber) {
            return $left->value === $right->value;
        }
        
        // Handle function calls
        if ($left instanceof Node\Expr\FuncCall && $right instanceof Node\Expr\FuncCall) {
            return $this->areNodesStructurallyEquivalent($left->name, $right->name) &&
                   $this->areArgumentsEquivalent($left->args, $right->args);
        }
        
        // Handle static calls
        if ($left instanceof Node\Expr\StaticCall && $right instanceof Node\Expr\StaticCall) {
            return $this->areNodesStructurallyEquivalent($left->class, $right->class) &&
                   $this->areNodesStructurallyEquivalent($left->name, $right->name) &&
                   $this->areArgumentsEquivalent($left->args, $right->args);
        }
        
        // For other node types, fall back to class comparison and attributes
        if ($left::class !== $right::class) {
            return false;
        }
        
        // As a last resort, compare the text representation (like Java fallback)
        return $left->__toString() === $right->__toString();
    }
    
    /**
     * @param array<Node\Arg> $leftArgs
     * @param array<Node\Arg> $rightArgs
     */
    private function areArgumentsEquivalent(array $leftArgs, array $rightArgs): bool
    {
        if (count($leftArgs) !== count($rightArgs)) {
            return false;
        }
        
        for ($i = 0; $i < count($leftArgs); $i++) {
            if (!$this->areNodesStructurallyEquivalent($leftArgs[$i]->value, $rightArgs[$i]->value)) {
                return false;
            }
        }
        
        return true;
    }

    private function getNodeText(Node $node): string
    {
        // Simple text extraction - in practice, you'd want more sophisticated handling
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return '$' . $node->name;
        }
        if ($node instanceof String_) {
            return '\'' . $node->value . '\'';
        }
        if ($node instanceof Node\Scalar\LNumber) {
            return (string) $node->value;
        }
        return 'expression';
    }
}