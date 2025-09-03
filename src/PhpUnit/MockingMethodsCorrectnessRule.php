<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\PhpUnit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects PHPUnit methods mocking issues.
 *
 * This rule identifies:
 * - Using willReturn(returnCallback(...)) or willReturn(returnValue(...)) instead of will(returnCallback(...)) or will(returnValue(...))
 * - Attempting to mock methods that don't exist on the class
 * - Attempting to mock final methods
 *
 * @implements Rule<MethodCall>
 */
class MockingMethodsCorrectnessRule implements Rule
{
    private const string MESSAGE_WILL_METHOD = "It probably was intended to use '->will(...)' here.";
    private const string MESSAGE_UNRESOLVED_METHOD = "The method was not resolved, perhaps it doesn't exist.";
    private const string MESSAGE_FINAL_METHOD = "The method is final hence can not be mocked.";

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {

        $methodName = $this->getMethodName($node);
        if ($methodName === null) {
            return [];
        }

        // Check for willReturn with returnCallback or returnValue
        if ($methodName === 'willReturn') {
            return $this->checkWillReturnUsage($node, $scope);
        }

        // Check for method() calls on mocks
        if ($methodName === 'method') {
            return $this->checkMethodCall($node, $scope);
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkWillReturnUsage(MethodCall $node, Scope $scope): array
    {
        if (count($node->getArgs()) !== 1) {
            return [];
        }

        $arg = $node->getArgs()[0]->value;
        if (!$arg instanceof MethodCall) {
            return [];
        }

        $innerMethodName = $this->getMethodName($arg);
        if ($innerMethodName === null) {
            return [];
        }

        if ($innerMethodName === 'returnCallback' || $innerMethodName === 'returnValue') {
            return [
                RuleErrorBuilder::message(self::MESSAGE_WILL_METHOD)
                    ->identifier('phpunit.mocking.willMethod')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function checkMethodCall(MethodCall $node, Scope $scope): array
    {
        if (count($node->getArgs()) !== 1) {
            return [];
        }

        $arg = $node->getArgs()[0]->value;
        if (!$arg instanceof String_) {
            return [];
        }

        $methodName = $arg->value;

        // Check if this is a mock context (has expects() or getMock() in the chain)
        if (!$this->isInMockContext($node, $scope)) {
            return [];
        }

        // Try to resolve the class being mocked
        $mockedClass = $this->resolveMockedClass($node, $scope);
        if ($mockedClass === null) {
            return [];
        }

        // Check if class exists
        if (!$this->reflectionProvider->hasClass($mockedClass)) {
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($mockedClass);

        // Check if method exists
        if (!$classReflection->hasMethod($methodName)) {
            return [
                RuleErrorBuilder::message(self::MESSAGE_UNRESOLVED_METHOD)
                    ->line($arg->getStartLine())
                    ->build(),
            ];
        }

        $methodReflection = $classReflection->getMethod($methodName, $scope);

        // Check if method is final
        if ($methodReflection->isFinal()->yes()) {
            return [
                RuleErrorBuilder::message(self::MESSAGE_FINAL_METHOD)
                    ->line($arg->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function getMethodName(MethodCall $node): ?string
    {
        if ($node->name instanceof Node\Identifier) {
            return $node->name->name;
        }

        return null;
    }

    private function isInMockContext(MethodCall $node, Scope $scope): bool
    {
        // Walk up the method call chain to find expects() or getMock()
        $current = $node->var;

        while ($current instanceof MethodCall) {
            $methodName = $this->getMethodName($current);
            if ($methodName === 'expects' || $methodName === 'getMock') {
                return true;
            }
            $current = $current->var;
        }

        return false;
    }

    private function resolveMockedClass(MethodCall $node, Scope $scope): ?string
    {
        // Walk up the chain to find getMockBuilder() call
        $current = $node->var;

        while ($current instanceof MethodCall) {
            $methodName = $this->getMethodName($current);
            if ($methodName === 'getMockBuilder') {
                if (count($current->getArgs()) === 1) {
                    $arg = $current->getArgs()[0]->value;
                    $argType = $scope->getType($arg);
                    foreach ($argType->getConstantStrings() as $constantString) {
                        return $constantString->getValue();
                    }
                }
            }
            $current = $current->var;
        }

        return null;
    }
}