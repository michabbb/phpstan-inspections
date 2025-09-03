<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unused constructor dependencies in dependency injection context.
 *
 * This rule identifies private properties that are stored in the constructor
 * but never used elsewhere in the class. Such properties may indicate dead code
 * or forgotten dependencies that should be removed.
 *
 * The rule analyzes:
 * - Private, non-static properties (excluding constants)
 * - Properties assigned in the constructor
 * - Usage of these properties in other methods (excluding constructor)
 *
 * Properties with PHPDoc tags containing uppercase letters are excluded
 * from analysis, as they may be used for documentation purposes.
 *
 * @implements Rule<Class_>
 */
class UnusedConstructorDependenciesRule implements Rule
{
    private const string MESSAGE = 'Property is used only in constructor, perhaps we are dealing with dead code here.';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        // Skip anonymous classes
        if ($node->name === null) {
            return [];
        }

        // Skip classes without constructor or properties
        if ($node->getMethod('__construct') === null || empty($node->getProperties())) {
            return [];
        }

        $privateProperties = $this->getPrivateProperties($node);
        if (empty($privateProperties)) {
            return [];
        }

        $constructorAssignments = $this->getConstructorAssignments($node->getMethod('__construct'), $privateProperties);
        if (empty($constructorAssignments)) {
            return [];
        }

        $propertyUsages = $this->getPropertyUsagesInMethods($node, $privateProperties);

        $errors = [];
        foreach ($constructorAssignments as $propertyName => $assignmentNode) {
            if (!isset($propertyUsages[$propertyName])) {
                $errors[] = RuleErrorBuilder::message(self::MESSAGE)
                    ->identifier('constructor.unusedDependency')
                    ->line($assignmentNode->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return array<string, PropertyProperty>
     */
    private function getPrivateProperties(Class_ $node): array
    {
        $privateProperties = [];

        foreach ($node->getProperties() as $property) {
            // Skip static properties
            if ($property->isStatic()) {
                continue;
            }

            // Only private properties
            if (!$property->isPrivate()) {
                continue;
            }

            // Skip properties with PHPDoc tags (similar to Java inspector logic)
            if ($this->hasAnnotatedDocComment($property)) {
                continue;
            }

            foreach ($property->props as $propertyProperty) {
                $propertyName = $propertyProperty->name->toString();
                $privateProperties[$propertyName] = $propertyProperty;
            }
        }

        return $privateProperties;
    }

    private function hasAnnotatedDocComment(Property $property): bool
    {
        $docComment = $property->getDocComment();
        if ($docComment === null) {
            return false;
        }

        // Check for PHPDoc tags with uppercase letters (similar to Java logic)
        $docText = $docComment->getText();
        if (preg_match('/@\w*[A-Z]\w*/', $docText)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, PropertyProperty> $privateProperties
     * @return array<string, Node>
     */
    private function getConstructorAssignments(ClassMethod $constructor, array $privateProperties): array
    {
        $assignments = [];

        // Use NodeTraverser to find property assignments in constructor
        $traverser = new NodeTraverser();
        $visitor = new class($privateProperties) extends NodeVisitorAbstract {
            /** @var array<string, PropertyProperty> */
            private array $privateProperties;
            /** @var array<string, Node> */
            public array $assignments = [];

            public function __construct(array $privateProperties)
            {
                $this->privateProperties = $privateProperties;
            }

            public function enterNode(Node $node)
            {
                // Look for assignments like $this->property = $value
                if ($node instanceof Node\Expr\Assign) {
                    $var = $node->var;
                    if ($var instanceof PropertyFetch) {
                        $propertyName = $this->getPropertyName($var);
                        if ($propertyName !== null && isset($this->privateProperties[$propertyName])) {
                            $this->assignments[$propertyName] = $node;
                        }
                    }
                }
                return null;
            }

            private function getPropertyName(PropertyFetch $propertyFetch): ?string
            {
                // Check if it's $this->property
                if ($propertyFetch->var instanceof Variable &&
                    $propertyFetch->var->name === 'this' &&
                    $propertyFetch->name instanceof Node\Identifier) {
                    return $propertyFetch->name->toString();
                }
                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($constructor->stmts);

        return $visitor->assignments;
    }

    /**
     * @param array<string, PropertyProperty> $privateProperties
     * @return array<string, true>
     */
    private function getPropertyUsagesInMethods(Class_ $node, array $privateProperties): array
    {
        $usages = [];

        foreach ($node->getMethods() as $method) {
            // Skip constructor
            if ($method->name->toString() === '__construct') {
                continue;
            }

            // Skip abstract methods
            if ($method->isAbstract()) {
                continue;
            }

            $methodUsages = $this->findPropertyUsagesInMethod($method, $privateProperties);
            foreach ($methodUsages as $propertyName => $used) {
                $usages[$propertyName] = true;
            }
        }

        return $usages;
    }

    /**
     * @param array<string, PropertyProperty> $privateProperties
     * @return array<string, true>
     */
    private function findPropertyUsagesInMethod(ClassMethod $method, array $privateProperties): array
    {
        $usages = [];

        $traverser = new NodeTraverser();
        $visitor = new class($privateProperties) extends NodeVisitorAbstract {
            /** @var array<string, PropertyProperty> */
            private array $privateProperties;
            /** @var array<string, true> */
            public array $usages = [];

            public function __construct(array $privateProperties)
            {
                $this->privateProperties = $privateProperties;
            }

            public function enterNode(Node $node)
            {
                // Look for property fetches like $this->property
                if ($node instanceof PropertyFetch) {
                    $propertyName = $this->getPropertyName($node);
                    if ($propertyName !== null && isset($this->privateProperties[$propertyName])) {
                        $this->usages[$propertyName] = true;
                    }
                }
                return null;
            }

            private function getPropertyName(PropertyFetch $propertyFetch): ?string
            {
                if ($propertyFetch->var instanceof Variable &&
                    $propertyFetch->var->name === 'this' &&
                    $propertyFetch->name instanceof Node\Identifier) {
                    return $propertyFetch->name->toString();
                }
                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($method->stmts);

        return $visitor->usages;
    }
}