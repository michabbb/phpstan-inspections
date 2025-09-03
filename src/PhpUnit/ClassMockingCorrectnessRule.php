<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\PhpUnit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Detects incorrect class mocking patterns in PHPUnit and PhpSpec.
 *
 * This rule identifies issues with mocking classes that cannot be mocked properly:
 * - Final classes cannot be mocked due to reflection errors
 * - Traits should use getMockForTrait() instead of createMock()
 * - Abstract classes should use getMockForAbstractClass() instead of getMockBuilder()
 * - Constructor parameters must be handled when using getMockBuilder()->getMock()
 * - PhpSpec ObjectBehavior classes should not use final classes in method parameters
 *
 * @implements Rule<Node>
 */
class ClassMockingCorrectnessRule implements Rule
{
    private const string MESSAGE_FINAL = 'Causes reflection errors as the referenced class is final.';
    private const string MESSAGE_TRAIT = 'Causes reflection errors as the referenced class is a trait.';
    private const string MESSAGE_NEEDS_ABSTRACT = 'Needs an abstract class here.';
    private const string MESSAGE_NEEDS_TRAIT = 'Needs a trait here.';
    private const string MESSAGE_MOCK_TRAIT = 'Perhaps it was intended to mock it with getMockForTrait method.';
    private const string MESSAGE_MOCK_ABSTRACT = 'Perhaps it was intended to mock it with getMockForAbstractClass method.';
    private const string MESSAGE_MOCK_CONSTRUCTOR = 'Needs constructor to be disabled or supplied with arguments.';

    /** @var array<string, string> */
    private const array METHODS = [
        '\\PHPUnit\\Framework\\TestCase.getMockBuilder' => 'getMockBuilder',
        '\\PHPUnit\\Framework\\TestCase.getMock' => 'getMock',
        '\\PHPUnit\\Framework\\TestCase.getMockClass' => 'getMockClass',
        '\\PHPUnit\\Framework\\TestCase.createMock' => 'createMock',
        '\\PHPUnit\\Framework\\TestCase.getMockForTrait' => 'getMockForTrait',
        '\\PHPUnit\\Framework\\TestCase.getMockForAbstractClass' => 'getMockForAbstractClass',
        '\\PHPUnit_Framework_TestCase.getMockBuilder' => 'getMockBuilder',
        '\\PHPUnit_Framework_TestCase.getMock' => 'getMock',
        '\\PHPUnit_Framework_TestCase.getMockClass' => 'getMockClass',
        '\\PHPUnit_Framework_MockObject_Generator.getMock' => 'getMock',
        '\\PHPUnit_Framework_MockObject_MockBuilder.getMock' => 'getMock',
        '\\Prophecy\\Prophet.prophesize' => 'prophesize',
        '\\Prophecy\\Prophecy\\ObjectProphecy.willExtend' => 'willExtend',
    ];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Handle PHPUnit/PhpSpec method calls
        if ($node instanceof MethodCall) {
            $errors = array_merge($errors, $this->processMethodCall($node, $scope));
        }

        // Handle PhpSpec ObjectBehavior classes
        if ($node instanceof Class_) {
            $errors = array_merge($errors, $this->processClass($node, $scope));
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function processMethodCall(MethodCall $node, Scope $scope): array
    {
        $errors = [];

        $methodName = $this->getMethodName($node);
        if ($methodName === null) {
            return $errors;
        }

        // Only process specific methods we care about first
        if (!in_array($methodName, array_values(self::METHODS), true)) {
            return $errors;
        }

        $args = $node->getArgs();
        if (empty($args)) {
            return $errors;
        }

        $referencedClass = $this->getReferencedClass($args[0], $scope);
        if ($referencedClass === null) {
            return $errors;
        }

        $errors = array_merge($errors, $this->checkMockingIssues($methodName, $referencedClass, $node, $scope, $args[0]));

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function processClass(Class_ $node, Scope $scope): array
    {
        $errors = [];

        // Check if this is a PhpSpec ObjectBehavior class
        $parentClass = $this->getParentClass($node, $scope);
        if ($parentClass === null || $parentClass !== '\\PhpSpec\\ObjectBehavior') {
            return $errors;
        }

        // Check method parameters for final classes
        foreach ($node->getMethods() as $method) {
            foreach ($method->getParams() as $param) {
                $paramType = $param->type;
                if ($paramType === null) {
                    continue;
                }

                $className = $this->extractClassNameFromType($paramType);
                if ($className === null) {
                    continue;
                }

                $classReflection = $this->reflectionProvider->getClass($className);
                if ($classReflection->isFinal()) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_FINAL)
                        ->identifier('phpunit.mocking.finalClass')
                        ->line($param->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }

    private function isPhpUnitRelatedClass(string $className): bool
    {
        // Check if the class is a PHPUnit TestCase or MockBuilder
        $phpunitClasses = [
            'PHPUnit\\Framework\\TestCase',
            'PHPUnit_Framework_TestCase',
            'PHPUnit\\Framework\\MockObject\\MockBuilder',
            'PHPUnit_Framework_MockObject_MockBuilder',
            'PHPUnit_Framework_MockObject_Generator',
        ];

        // Direct match first
        foreach ($phpunitClasses as $phpunitClass) {
            if ($className === $phpunitClass) {
                return true;
            }
        }

        // Check inheritance using ReflectionProvider
        try {
            $classReflection = $this->reflectionProvider->getClass($className);
            foreach ($phpunitClasses as $phpunitClass) {
                if ($classReflection->isSubclassOf($phpunitClass)) {
                    return true;
                }
            }
        } catch (\PHPStan\Reflection\ClassNotFoundException) {
            // Class not found, can't check inheritance
        }

        return false;
    }

    private function getMethodName(MethodCall $node): ?string
    {
        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        return $node->name->name;
    }

    private function getMethodFQN(MethodCall $node, Scope $scope): ?string
    {
        $calledOnType = $scope->getType($node->var);

        if (!$calledOnType instanceof ObjectType) {
            return null;
        }

        $className = $calledOnType->getClassName();
        $methodName = $this->getMethodName($node);

        if ($methodName === null) {
            return null;
        }

        return $className . '.' . $methodName;
    }

    private function getReferencedClass(Node\Arg $arg, Scope $scope): ?string
    {
        $argType = $scope->getType($arg->value);

        // Handle string literals (class names as strings)
        if ($argType->isString()->yes()) {
            $constantStrings = $argType->getConstantStrings();
            if (!empty($constantStrings)) {
                return $constantStrings[0]->getValue();
            }
        }

        // Handle ::class constants
        if ($arg->value instanceof Node\Expr\ClassConstFetch) {
            if ($arg->value->name instanceof Node\Identifier && $arg->value->name->name === 'class') {
                if ($arg->value->class instanceof Node\Name) {
                    return $arg->value->class->toString();
                }
            }
        }

        return null;
    }

    /**
     * @return list<RuleError>
     */
    private function checkMockingIssues(string $methodName, string $className, MethodCall $node, Scope $scope, Node\Arg $arg): array
    {
        $errors = [];

        try {
            $classReflection = $this->reflectionProvider->getClass($className);
        } catch (\PHPStan\Reflection\ClassNotFoundException) {
            return $errors;
        }

        switch ($methodName) {
            case 'createMock':
                if ($classReflection->isTrait()) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_TRAIT)
                        ->identifier('phpunit.mocking.traitWithCreateMock')
                        ->line($arg->getStartLine())
                        ->build();
                } elseif ($classReflection->isFinal()) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_FINAL)
                        ->identifier('phpunit.mocking.finalClass')
                        ->line($arg->getStartLine())
                        ->build();
                }
                break;

            case 'getMockBuilder':
                $parentMethodCall = $this->getParentMethodCall($node);
                $parentMethodName = $parentMethodCall !== null ? $this->getMethodName($parentMethodCall) : null;

                if ($classReflection->isAbstract() && !$classReflection->isInterface()) {
                    if ($parentMethodName === null) {
                        $errors[] = RuleErrorBuilder::message(self::MESSAGE_MOCK_ABSTRACT)
                            ->identifier('phpunit.mocking.abstractClass')
                            ->line($arg->getStartLine())
                            ->build();
                    }
                } elseif ($classReflection->isTrait()) {
                    if ($parentMethodName === null) {
                        $errors[] = RuleErrorBuilder::message(self::MESSAGE_MOCK_TRAIT)
                            ->identifier('phpunit.mocking.traitWithMockBuilder')
                            ->line($arg->getStartLine())
                            ->build();
                    }
                } elseif ($classReflection->isFinal()) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_FINAL)
                        ->identifier('phpunit.mocking.finalClass')
                        ->line($arg->getStartLine())
                        ->build();
                }

                // Check constructor parameters when getMock() is called
                if ($parentMethodName === 'getMock') {
                    $constructor = $classReflection->getConstructor();
                    if ($constructor !== null) {
                        $hasRequiredParams = false;
                        foreach ($constructor->getParameters() as $param) {
                            if (!$param->isOptional()) {
                                $hasRequiredParams = true;
                                break;
                            }
                        }

                        if ($hasRequiredParams) {
                            $errors[] = RuleErrorBuilder::message(self::MESSAGE_MOCK_CONSTRUCTOR)
                                ->identifier('phpunit.mocking.constructorParams')
                                ->line($arg->getStartLine())
                                ->build();
                        }
                    }
                }
                break;

            case 'getMockForTrait':
                if (!$classReflection->isTrait()) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_NEEDS_TRAIT)
                        ->identifier('phpunit.mocking.notTrait')
                        ->line($arg->getStartLine())
                        ->build();
                }
                break;

            case 'getMockForAbstractClass':
                if (!$classReflection->isAbstract()) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_NEEDS_ABSTRACT)
                        ->identifier('phpunit.mocking.notAbstract')
                        ->line($arg->getStartLine())
                        ->build();
                }
                break;

            default:
                if ($classReflection->isFinal()) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_FINAL)
                        ->identifier('phpunit.mocking.finalClass')
                        ->line($arg->getStartLine())
                        ->build();
                }
                break;
        }

        return $errors;
    }

    private function getParentMethodCall(MethodCall $node): ?MethodCall
    {
        $parent = $node->getAttribute('parent');
        while ($parent !== null) {
            if ($parent instanceof MethodCall) {
                return $parent;
            }
            $parent = $parent->getAttribute('parent');
        }

        return null;
    }

    private function getParentClass(Class_ $node, Scope $scope): ?string
    {
        if (empty($node->extends)) {
            return null;
        }

        $extends = $node->extends;
        if (!$extends instanceof Node\Name) {
            return null;
        }

        return $extends->toString();
    }

    private function extractClassNameFromType(Node $type): ?string
    {
        if ($type instanceof Node\Name) {
            return $type->toString();
        }

        if ($type instanceof Node\NullableType && $type->type instanceof Node\Name) {
            return $type->type->toString();
        }

        return null;
    }
}