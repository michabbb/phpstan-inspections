<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Callable method validity rule mirroring CallableMethodValidityInspector from PhpStorm EA Extended.
 *
 * This rule checks whether callback methods referenced in is_callable() calls are properly
 * accessible (public) and have the correct static/non-static nature based on the callable format.
 *
 * For string literals like 'Class::method', the method should be static.
 * For arrays like ['Class', 'method'], the method should be static if the class reference
 * is a string type or class constant.
 *
 * @implements Rule<FuncCall>
 */
final class CallableMethodValidityRule implements Rule
{
    private const string MESSAGE_NOT_PUBLIC = "'%s' should be public (e.g. \$this usage in static context provokes fatal errors).";
    private const string MESSAGE_NOT_STATIC = "'%s' should be static (e.g. \$this usage in static context provokes fatal errors).";

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @param FuncCall $node */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isIsCallableCall($node)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) !== 1) {
            return [];
        }

        $callableArg = $args[0]->value;
        if (!$this->isValidCallableFormat($callableArg)) {
            return [];
        }

        return $this->analyzeCallable($callableArg, $scope, $node->getStartLine());
    }

    private function isIsCallableCall(FuncCall $funcCall): bool
    {
        return $funcCall->name instanceof Node\Name
            && $funcCall->name->toString() === 'is_callable';
    }

    private function isValidCallableFormat(Node $callable): bool
    {
        return $callable instanceof String_
            || ($callable instanceof Array_ && count($callable->items) === 2);
    }

    /**
     * @return list<RuleError>
     */
    private function analyzeCallable(Node $callable, Scope $scope, int $line): array
    {
        $errors = [];

        if ($callable instanceof String_) {
            $this->analyzeStringCallable($callable->value, $scope, $errors, $line);
        } elseif ($callable instanceof Array_) {
            $this->analyzeArrayCallable($callable, $scope, $errors, $line);
        }

        return $errors;
    }

    /**
     * @param list<RuleError> $errors
     */
    private function analyzeStringCallable(string $callableString, Scope $scope, array &$errors, int $line): void
    {
        if (!str_contains($callableString, '::')) {
            return;
        }

        [$className, $methodName] = explode('::', $callableString, 2);

        $this->validateMethod($className, $methodName, $scope, $errors, $line, true);
    }

    /**
     * @param list<RuleError> $errors
     */
    private function analyzeArrayCallable(Array_ $callable, Scope $scope, array &$errors, int $line): void
    {
        if (count($callable->items) !== 2) {
            return;
        }

        $classItem = $callable->items[0]?->value;
        $methodItem = $callable->items[1]?->value;

        if (!$methodItem instanceof String_) {
            return;
        }

        $methodName = $methodItem->value;
        $shouldBeStatic = $this->shouldMethodBeStatic($classItem, $scope);

        $className = $this->extractClassName($classItem, $scope);
        if ($className === null) {
            return;
        }

        $this->validateMethod($className, $methodName, $scope, $errors, $line, $shouldBeStatic);
    }

    private function shouldMethodBeStatic(?Node $classItem, Scope $scope): bool
    {
        if ($classItem === null) {
            return false;
        }

        // If it's a string literal, method should be static
        if ($classItem instanceof String_) {
            return true;
        }

        // If it's a class constant reference like ClassName::class, method should be static
        if ($classItem instanceof Node\Expr\ClassConstFetch) {
            return $classItem->name instanceof Node\Identifier
                && $classItem->name->toString() === 'class';
        }

        // Check the type of the expression
        $type = $scope->getType($classItem);
        $typeStrings = $type->getConstantStrings();

        foreach ($typeStrings as $constantString) {
            $typeValue = $constantString->getValue();
            if ($typeValue === 'string' || $typeValue === 'callable') {
                return true;
            }
        }

        return false;
    }

    private function extractClassName(?Node $classItem, Scope $scope): ?string
    {
        if ($classItem === null) {
            return null;
        }

        if ($classItem instanceof String_) {
            return $classItem->value;
        }

        if ($classItem instanceof Node\Expr\ClassConstFetch) {
            if ($classItem->class instanceof Node\Name) {
                return $classItem->class->toString();
            }
        }

        // Try to get class name from type
        $type = $scope->getType($classItem);
        $typeStrings = $type->getConstantStrings();

        foreach ($typeStrings as $constantString) {
            return $constantString->getValue();
        }

        return null;
    }

    /**
     * @param list<RuleError> $errors
     */
    private function validateMethod(string $className, string $methodName, Scope $scope, array &$errors, int $line, bool $shouldBeStatic): void
    {
        try {
            $classReflection = $this->reflectionProvider->getClass($className);
        } catch (\PHPStan\Reflection\ClassNotFoundException) {
            return; // Skip if class doesn't exist
        }

        if (!$classReflection->hasMethod($methodName)) {
            return; // Skip if method doesn't exist
        }

        $methodReflection = $classReflection->getMethod($methodName, $scope);

        // Check if method is public
        if (!$methodReflection->isPublic()) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(self::MESSAGE_NOT_PUBLIC, $methodName)
            )
                ->identifier('callableMethodValidity.notPublic')
                ->line($line)
                ->build();
        }

        // Check static requirement
        if ($shouldBeStatic && !$methodReflection->isStatic()) {
            $errors[] = RuleErrorBuilder::message(
                sprintf(self::MESSAGE_NOT_STATIC, $methodName)
            )
                ->identifier('callableMethodValidity.notStatic')
                ->line($line)
                ->build();
        }
    }
}