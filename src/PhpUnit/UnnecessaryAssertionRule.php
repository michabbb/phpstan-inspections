<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\PhpUnit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VoidType;

/**
 * Detects unnecessary PHPUnit assertions.
 *
 * This rule identifies:
 * - Type hint assertions that can be skipped when the argument implicitly declares the return type
 * - Mock expectations with ->expects(...->any()) that can be omitted
 *
 * @implements Rule<MethodCall>
 */
class UnnecessaryAssertionRule implements Rule
{
    private const string MESSAGE_RETURN_TYPE = 'This assertion can probably be skipped (argument implicitly declares return type).';
    private const string MESSAGE_EXPECTS_ANY = 'This assertion can probably be omitted (\'->expects(...->any())\' to be more specific).';

    /** @var array<string, int> */
    private const array TARGET_POSITIONS = [
        'assertInstanceOf' => 1,
        'assertEmpty' => 0,
        'assertNull' => 0,
        'assertInternalType' => 1,
    ];

    /** @var array<string, string> */
    private const array TARGET_TYPES = [
        'assertEmpty' => 'void',
        'assertNull' => 'void',
        'assertInstanceOf' => null,
        'assertInternalType' => null,
    ];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        $methodName = $this->getMethodName($node);
        if ($methodName === null) {
            return [];
        }

        // Check for mocking case: ->expects(...->any())
        if ($methodName === 'expects') {
            return $this->analyzeMockingAsserts($node, $scope);
        }

        // Check for type hint case: assertInstanceOf, assertEmpty, assertNull, assertInternalType
        if (array_key_exists($methodName, self::TARGET_POSITIONS)) {
            return $this->analyzeTypeHintCase($node, $scope, $methodName);
        }

        return [];
    }

    /**
     * @return array<RuleError>
     */
    private function analyzeMockingAsserts(MethodCall $node, Scope $scope): array
    {
        if (count($node->getArgs()) !== 1) {
            return [];
        }

        $arg = $node->getArgs()[0]->value;
        if (!$arg instanceof MethodCall) {
            return [];
        }

        $innerMethodName = $this->getMethodName($arg);
        if ($innerMethodName !== 'any') {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE_EXPECTS_ANY)
                ->identifier('phpunit.unnecessaryAssertion.expectsAny')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * @return array<RuleError>
     */
    private function analyzeTypeHintCase(MethodCall $node, Scope $scope, string $methodName): array
    {
        $position = self::TARGET_POSITIONS[$methodName];
        $args = $node->getArgs();

        if (count($args) <= $position) {
            return [];
        }

        $arg = $args[$position]->value;
        $argType = $scope->getType($arg);

        // Check if argument is a function call
        if (!$arg instanceof FuncCall) {
            return [];
        }

        // Get function name
        if (!$arg->name instanceof Name) {
            return [];
        }

        $functionName = $arg->name->toString();

        // Check if function exists and has return type
        if (!$this->reflectionProvider->hasFunction(new Name($functionName), $scope)) {
            return [];
        }

        $functionReflection = $this->reflectionProvider->getFunction(new Name($functionName), $scope);
        $returnType = $functionReflection->getVariants()[0]->getReturnType();

        // Skip if no return type declared
        if ($returnType instanceof VoidType) {
            return [];
        }

        // Determine expected type
        $expectedType = self::TARGET_TYPES[$methodName];

        if ($methodName === 'assertInstanceOf') {
            if (count($args) < 1) {
                return [];
            }

            $classArg = $args[0]->value;
            $classArgType = $scope->getType($classArg);

            if ($classArgType instanceof ConstantStringType) {
                $expectedType = $classArgType->getValue();
                if ($returnType->isSuperTypeOf(new ObjectType($expectedType))->yes()) {
                    return [
                        RuleErrorBuilder::message(self::MESSAGE_RETURN_TYPE)
                            ->identifier('phpunit.unnecessaryAssertion.returnType')
                            ->line($node->getStartLine())
                            ->build(),
                    ];
                }
            }

            return [];
        }

        // Check if types match
        if ($expectedType === null) {
            // For assertInstanceOf and assertInternalType, any declared return type makes the assertion unnecessary
            return [
                RuleErrorBuilder::message(self::MESSAGE_RETURN_TYPE)
                    ->identifier('phpunit.unnecessaryAssertion.returnType')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        // For assertEmpty and assertNull, check if return type matches expected
        if ($returnType instanceof ObjectType && $returnType->getClassName() === $expectedType) {
            return [
                RuleErrorBuilder::message(self::MESSAGE_RETURN_TYPE)
                    ->identifier('phpunit.unnecessaryAssertion.returnType')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function getMethodName(MethodCall $node): ?string
    {
        if ($node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }

        return null;
    }
}