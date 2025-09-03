# Comprehensive Guide to PHPStan Custom Rules

PHPStan is a powerful tool for static code analysis in PHP, helping developers detect errors during development. While the built-in rules already provide a solid foundation, Custom Rules allow for the implementation of project-specific standards and the enforcement of individual coding conventions[^1]. This comprehensive guide will walk you through all aspects of developing your own PHPStan rules.

![PHPStan Custom Rules Architecture - Workflow from PHP Code to Error Output](https://ppl-ai-code-interpreter-files.s3.amazonaws.com/web/direct-files/0f4d718a84e60553dd17d341b86a090e/3a6b906f-eb6f-42d0-b3f5-741ce0b927da/bee05289.png)

PHPStan Custom Rules Architecture - Workflow from PHP Code to Error Output

## Fundamentals and Introduction

### What Are PHPStan Custom Rules?

PHPStan Custom Rules are user-defined extensions that allow you to implement specific rules for your codebase[^1]. These rules go beyond the standard PHPStan checks and can enforce project-specific requirements, architectural decisions, or best practices[^2][^3].

**Typical Use Cases for Custom Rules:**

- **Architecture Enforcement**: Preventing direct instantiation of certain classes[^1]
- **Business Logic Separation**: Detecting business logic in controller classes[^2]
- **Naming Conventions**: Enforcing specific naming rules[^4]
- **Security Patterns**: Detecting insecure code patterns[^5]
- **Framework-specific Rules**: Laravel, Symfony, or other framework-specific checks[^3]

### When and Why to Use Custom Rules?

Custom Rules are particularly valuable when standard PHPStan rules are insufficient to enforce project-specific quality standards[^6]. They automate aspects of code review and reduce the need for manual checks[^2].

**Advantages of Custom Rules:**

- **Automation**: Repetitive checks are automated
- **Consistency**: Uniform enforcement of standards across the entire team
- **Early Error Detection**: Problems are identified during the development phase
- **Documentation**: Rules serve as living documentation of coding standards

### Prerequisites and Setup

**System Requirements:**

- PHP 7.4 or higher
- Composer for dependency management
- PHPStan 1.9 or higher (latest version recommended)[^7]

**Basic Installation:**

```bash
composer require --dev phpstan/phpstan
composer require --dev phpstan/extension-installer
```

The `phpstan/extension-installer` allows for the automatic loading of PHPStan extensions[^8].

## Architecture and Concepts

### Abstract Syntax Tree (AST)

The Abstract Syntax Tree (AST) is the structured representation of PHP code that PHPStan uses for analysis[^9]. Each part of the code is represented as a Node in the AST, which has specific properties and relationships to other Nodes[^10].

![PHPStan AST Node Type Hierarchy - Most Important Nodes for Custom Rules](https://ppl-ai-code-interpreter-files.s3.amazonaws.com/web/direct-files/0f4d718a84e60553dd17d341b86a090e/49508cdc-06e4-4f83-9964-57e09d115bba/c969eb9d.png)

PHPStan AST Node Type Hierarchy - Most Important Nodes for Custom Rules

**Key AST Concepts:**

- **Statements (Stmt)**: Control program flow and declare symbols[^9]
- **Expressions (Expr)**: Can be resolved to types[^9]
- **Node Hierarchy**: All nodes inherit from `PhpParser\Node`

### Rule Interface and Implementation

Every Custom Rule must implement the `PHPStan\Rules\Rule` interface[^1][^11]:

```php
interface Rule
{
    /**
     * @return class-string<TNodeType>
     */
    public function getNodeType(): string;
    
    /**
     * @param TNodeType $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array;
}
```

**Core Methods:**

- **`getNodeType()`**: Returns the AST node type the rule should react to[^1]
- **`processNode()`**: Analyzes the node and returns potential errors[^1]

### The Scope Object and Type System

The Scope object contains contextual information about the current analysis state[^12]:

- Current namespace and class context
- Types of variables and expressions
- Available methods and properties

**Important Scope Methods:**

```php
$scope->getType($expression);        // Get the type of an expression
$scope->isInClass();                // Check if in a class context
$scope->getClassReflection();       // Get current class information
$scope->getFunction();              // Get current function/method
```

## Creating Your First Custom Rule

### Setting Up the Project Structure

**Recommended Folder Structure:**

```
project/
├── src/
├── tests/
├── phpstan/
│   ├── Rules/
│   │   └── NoEmptyClassRule.php
│   └── Tests/
│       ├── Rules/
│       │   └── NoEmptyClassRuleTest.php
│       └── fixtures/
│           ├── empty-class.php
│           └── valid-class.php
├── phpstan.neon
└── composer.json
```

**Composer Configuration:**

```json
{
    "autoload-dev": {
        "psr-4": {
            "App\\PHPStan\\": "phpstan/",
            "Tests\\PHPStan\\": "phpstan/Tests/"
        }
    }
}
```

### Implementing a Simple Rule

A simple rule to detect empty classes:

```php
<?php declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
class NoEmptyClassRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (count($node->stmts) === 0) {
            return [
                RuleErrorBuilder::message('Empty classes are not allowed.')
                    ->identifier('class.empty')
                    ->build(),
            ];
        }

        return [];
    }
}
```

### Registration in phpstan.neon

```yaml
parameters:
    level: 8
    paths:
        - src/

services:
    -   class: App\PHPStan\Rules\NoEmptyClassRule
        tags:
            - phpstan.rules.rule
```

### Testing and Debugging the Rule

**Creating a Basic Test:**

```php
<?php declare(strict_types=1);

namespace Tests\PHPStan\Rules;

use App\PHPStan\Rules\NoEmptyClassRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoEmptyClassRule>
 */
class NoEmptyClassRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoEmptyClassRule();
    }

    public function testEmptyClassReportsError(): void
    {
        $this->analyse([__DIR__ . '/fixtures/empty-class.php'], [
            [
                'Empty classes are not allowed.',
                7, // line number
            ],
        ]);
    }
}
```

## Advanced Rule Development

### Using the RuleErrorBuilder

The `RuleErrorBuilder` allows for the creation of structured error messages[^13]:

```php
return [
    RuleErrorBuilder::message('Custom error message')
        ->identifier('custom.identifier')
        ->tip('Helpful tip for developers')
        ->line($specificLineNumber)
        ->nonIgnorable()
        ->build(),
];
```

**Available Methods:**

- `->identifier()`: Error identifier for targeted ignoring[^14]
- `->tip()`: Helpful tip for problem-solving[^13]
- `->line()`: Specific line number[^13]
- `->nonIgnorable()`: The error cannot be ignored[^13]

### Defining Error Identifiers

Error identifiers follow the format `category.subtype` and allow for targeted ignoring of errors[^14]:

```php
// @phpstan-ignore class.empty
class EmptyClass {}
```

**Best Practices for Identifiers:**

- Use descriptive names
- Maintain consistent categorization
- Document the available identifiers

### Scope Analysis for Complex Rules

An advanced rule with context analysis:

```php
public function processNode(Node $node, Scope $scope): array
{
    if (!$node->class instanceof Node\Name) {
        return [];
    }

    $className = $node->class->toString();
    
    // Context-dependent checks
    if ($scope->isInClass()) {
        $currentClass = $scope->getClassReflection()->getName();
        if (str_ends_with($currentClass, 'Factory')) {
            return []; // Exception for Factory classes
        }
    }

    // Type analysis
    $calledOnType = $scope->getType($methodCall->var);
    
    return $errors;
}
```

### Making Rules Configurable and Using Dependencies

For maximum reusability and complexity, it is essential to be able to configure rules externally and use services via Dependency Injection (DI). PHPStan internally uses a DI container (based on `nette/di`), which makes this elegant.

#### Passing Parameters from `phpstan.neon`

Every rule registered in `phpstan.neon` is a service. Therefore, you can pass values to your rule's constructor using the `arguments` key in the service definition array.

**Example:** The `ClassNamingRule` should get its suffix rules from the configuration.

**`phpstan.neon` Configuration:**

```yaml
parameters:
    # Define custom parameters for reusability
    namingRules:
        # Namespace pattern: required suffix
        'App\Services': 'Service'
        'App\Repositories': 'Repository'

services:
    -   class: App\PHPStan\Rules\ClassNamingRule
        # Pass arguments to the constructor
        arguments:
            # %namingRules% references the parameter defined above
            suffixRules: %namingRules%
        tags:
            - phpstan.rules.rule
```

The constructor of the `ClassNamingRule` receives this array directly.

#### Dependency Injection for Advanced Logic

More complex rules often need access to PHPStan's internal services, for example, to analyze class reflections. These can be easily requested via constructor injection.

**Example:** A rule that uses the `ReflectionProvider`.

```php
<?php declare(strict_types=1);

namespace App\PHPStan\Rules;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<...>
 */
class SomeAdvancedRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    // ... getNodeType() and processNode()
    // In processNode, $this->reflectionProvider can now be used
}
```

PHPStan's DI container recognizes the type hint (`ReflectionProvider`) and automatically injects the correct service instance (autowiring).

## Testing and Quality Assurance

### RuleTestCase Setup

PHPStan provides a special test base for custom rules[^15]:

```php
/**
 * @extends RuleTestCase<CustomRule>
 */
class CustomRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CustomRule();
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../extension.neon'];
    }
}
```

### Tests for Rules with Dependencies

If your rule has dependencies in its constructor, you must provide them manually in the test. The `getRule()` method is the place for this.

**Example for the `ClassNamingRule`:**

```php
/**
 * @extends RuleTestCase<ClassNamingRule>
 */
class ClassNamingRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        // Provide the configuration here for the test case
        $suffixRules = [
            'Tests\Fixtures\Services': 'Service',
        ];

        return new ClassNamingRule($suffixRules);
    }

    // ... tests
}
```

For service dependencies like the `ReflectionProvider`, you can either create a real instance (if easily possible) or a mock in the test. For most PHPStan services, you can use `self::getContainer()->getByType(...)` to get an instance from the test container.

```php
protected function getRule(): Rule
{
    // Get ReflectionProvider from the test container
    $reflectionProvider = self::getContainer()->getByType(ReflectionProvider::class);

    return new SomeAdvancedRule($reflectionProvider);
}
```

### Creating Fixture Files

**Test fixtures** should cover various scenarios:

```php
<?php
// fixtures/test-cases.php

namespace Tests\Fixtures;

// Positive case - should trigger error
class EmptyClass
{
}

// Negative case - should pass
class ValidClass  
{
    private string $property;
    
    public function method(): void {}
}

// Edge case - abstract class
abstract class AbstractClass
{
}
```

### Different Test Scenarios

**Important Test Categories:**

1.  **Happy Path**: Rule correctly identifies problems
2.  **False Positives**: Rule does not trigger on valid code
3.  **Edge Cases**: Special cases and boundary values
4.  **Performance**: Tests with larger codebases

```php
public function testComplexScenarios(): void
{
    $this->analyse([
        __DIR__ . '/fixtures/happy-path.php',
        __DIR__ . '/fixtures/edge-cases.php',
    ], [
        ['Expected error message', 15],
        ['Another error message', 23],
    ]);
}
```

## Practical Examples

### Forbidding Business Logic in Controllers

A rule to enforce Separation of Concerns[^2]:

```php
/**
 * @implements Rule<ClassMethod>
 */
class ControllerBusinessLogicRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        $className = $classReflection->getName();

        // Only check controller classes
        if (!str_ends_with($className, 'Controller')) {
            return [];
        }

        // Check for business logic indicators
        foreach ($node->stmts ?? [] as $stmt) {
            if ($this->containsBusinessLogic($stmt)) {
                return [
                    RuleErrorBuilder::message(
                        sprintf(
                            'Method "%s::%s" contains business logic. Move it to a service class.',
                            $className,
                            $node->name->toString()
                        )
                    )
                    ->identifier('controller.businessLogic')
                    ->tip('Consider using the Command or Action pattern')
                    ->build(),
                ];
            }
        }

        return [];
    }

    private function containsBusinessLogic(Node $stmt): bool
    {
        return $stmt instanceof Node\Stmt\If_ 
            || $stmt instanceof Node\Stmt\For_ 
            || $stmt instanceof Node\Stmt\Foreach_ 
            || $stmt instanceof Node\Stmt\While_ 
            || $stmt instanceof Node\Stmt\Switch_;
    }
}
```

### Custom Naming Conventions

A rule for specific naming conventions, configured via `phpstan.neon`.

```php
/**
 * @implements Rule<Class_>
 */
class ClassNamingRule implements Rule
{
    /** @var array<string, string> */
    private array $suffixRules;

    /**
     * @param array<string, string> $suffixRules
     */
    public function __construct(array $suffixRules)
    {
        $this->suffixRules = $suffixRules;
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name === null) {
            // Anonymous classes
            return [];
        }
    
        $className = $node->name->toString();
        $namespace = $scope->getNamespace() ?? '';
        
        foreach ($this->suffixRules as $pattern => $requiredSuffix) {
            // One could use regex matching here instead of str_contains
            if (str_contains($namespace, $pattern)) {
                if (!str_ends_with($className, $requiredSuffix)) {
                    return [
                        RuleErrorBuilder::message(
                            sprintf('Class "%s" in namespace "%s" must end with "%s".', $className, $namespace, $requiredSuffix)
                        )
                        ->identifier('naming.classSuffix')
                        ->tip(sprintf('Rename the class to "%s%s".', $className, $requiredSuffix))
                        ->build(),
                    ];
                }
            }
        }

        return [];
    }
}
```

## Advanced Topics

### Collectors for Project-Wide Analysis

Collectors enable analysis across multiple files[^16]:

```php
/**
 * @implements Collector<Node\Stmt\Class_, array{string, int}>
 */
class UnusedClassCollector implements Collector
{
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return [
            $scope->getClassReflection()->getName(),
            $node->getStartLine(),
        ];
    }
}
```

### Extension as a Composer Package

For reusability, rules can be developed as a Composer package[^8]:

```json
{
    "name": "company/phpstan-custom-rules",
    "type": "phpstan-extension",
    "extra": {
        "phpstan": {
            "includes": [
                "extension.neon"
            ]
        }
    },
    "require": {
        "phpstan/phpstan": "^1.9"
    }
}
```

**extension.neon:**

```yaml
services:
    -   class: Company\PHPStan\Rules\CustomRule
        tags:
            - phpstan.rules.rule
```

## Best Practices and Troubleshooting

### Avoiding Common Pitfalls

**The most common problems and their solutions:**

1.  **Rule is not executed**: Incorrect node type in `getNodeType()`
    - Solution: Inspect the AST with `php-parse`
2.  **Class not found**: Autoloading issues
    - Solution: Run `composer dump-autoload`
3.  **Too many false positives**: Insufficient context analysis
    - Solution: Use the Scope object for better context

### Debugging Strategies

**Effective debugging techniques:**

```php
// Find out the node type
public function processNode(Node $node, Scope $scope): array
{
    // Temporary debugging
    var_dump(get_class($node));
    var_dump($node->getAttributes());
    
    // Production code...
}
```

**AST Inspection:**

```bash
vendor/bin/php-parse --dump debug-file.php
```

### Performance Optimization

**Optimization Strategies:**

- **Early Returns**: Avoid unnecessary checks
- **Node Type Specificity**: Use the most specific node types
- **Minimize Scope Queries**: Cache expensive operations
- **Batch Processing**: Combine multiple checks in one rule

The development process of a custom rule follows a structured 8-step approach, requiring about 4 hours for medium complexity. Careful planning and systematic testing are crucial for successful custom rules.

## Conclusion

PHPStan Custom Rules offer a powerful way to automatically enforce project-specific quality standards[^1]. By using the Abstract Syntax Tree, complex code patterns can be detected and architectural decisions can be validated[^9].

The investment in Custom Rules pays off in the long run through reduced code review time, increased code quality, and better consistency within the development team[^2]. With the right tools, systematic testing, and continuous improvement, Custom Rules can become a valuable part of the development toolchain.

**Next Steps:**

- Start with a simple rule for a specific problem in your project
- Gradually expand the complexity and scope of functionality
- Document your rules for the team
- Share successful rules with the community

The combination of solid fundamentals, practical examples, and continuous improvement enables the successful use of PHPStan Custom Rules for better code quality.

## Advanced Extension Points (Beyond Rules)

This section supplements everything that is important for more complex analyses in practice but is often missing from basic tutorials.

### Cheatsheet: Common Extension Points with Tags

| Purpose | Interface | Typical Registration (NEON Tag) |
|---|---|---|
| Dynamic Return Type (Functions) | `PHPStan\Type\DynamicFunctionReturnTypeExtension` | `phpstan.broker.dynamicFunctionReturnTypeExtension` |
| Dynamic Return Type (Methods) | `PHPStan\Type\DynamicMethodReturnTypeExtension` | `phpstan.broker.dynamicMethodReturnTypeExtension` |
| Dynamic Return Type (Static Methods) | `PHPStan\Type\DynamicStaticMethodReturnTypeExtension` | `phpstan.broker.dynamicStaticMethodReturnTypeExtension` |
| **Dynamic Exception Type (Methods)** | `PHPStan\Type\DynamicMethodThrowTypeExtension` | `phpstan.dynamicMethodThrowTypeExtension` |
| Type Specifying (Functions) | `PHPStan\Type\FunctionTypeSpecifyingExtension` | `phpstan.typeSpecifier.functionTypeSpecifyingExtension` |
| Type Specifying (Methods) | `PHPStan\Type\MethodTypeSpecifyingExtension` | `phpstan.typeSpecifier.methodTypeSpecifyingExtension` |
| Provide Magic Methods | `PHPStan\Reflection\MethodsClassReflectionExtension` | `phpstan.broker.methodsClassReflectionExtension` |
| Provide Magic Properties | `PHPStan\Reflection\PropertiesClassReflectionExtension` | `phpstan.broker.propertiesClassReflectionExtension` |
| Collectors (collect project-wide data) | `PHPStan\Collectors\Collector` | `phpstan.collector` |
| **Ignore Errors Programmatically** | `PHPStan\Analyser\IgnoreErrorExtension` | `phpstan.ignoreErrorExtension` |
| **Resolve Custom PHPDoc Types** | `PHPStan\PhpDoc\TypeNodeResolverExtension` | `phpstan.phpDoc.typeNodeResolverExtension` |
| **Custom Deprecation Logic** | `PHPStan\Reflection\Deprecation\ClassDeprecationExtension` | `phpstan.classDeprecationExtension` |
| **Control Cache Invalidation** | `PHPStan\Rules\RuleLevel\ResultCacheMetaExtension` | `phpstan.resultCacheMetaExtension` |

Note: Tag names are stable in the 1.x branch. For major version jumps, check the official "Developing extensions" pages.

### Example: DynamicMethodReturnTypeExtension

When the return type depends on input arguments, Dynamic Return Type Extensions are the right tool.

```php
<?php declare(strict_types=1);

namespace App\PHPStan\Extensions;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

class ContainerMakeReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    public function getClass(): string
    {
        return \App\\Container::class; // Class on which the method exists
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'make';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): Type {
        if (count($methodCall->getArgs()) === 0) {
            return $methodReflection->getVariants()[0]->getReturnType();
        }

        $argType = $scope->getType($methodCall->getArgs()[0]->value);
        foreach ($argType->getConstantStrings() as $constString) {
            // for ConstantString "Foo\\Bar" -> ObjectType(Foo\\Bar)
            if ($constString instanceof ConstantStringType) {
                return new ObjectType($constString->getValue());
            }
        }

        // Fallback to declared return type
        return $methodReflection->getVariants()[0]->getReturnType();
    }
}
```

Registration in `phpstan.neon`:

```neon
services:
    -   class: App\PHPStan\Extensions\ContainerMakeReturnTypeExtension
        tags: [phpstan.broker.dynamicMethodReturnTypeExtension]
```

### Example: DynamicMethodThrowTypeExtension

This extension is ideal if a method only throws an exception under certain conditions.

```php
<?php declare(strict_types=1);

namespace App\PHPStan\Extensions;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\Type;

class ComponentContainerThrowTypeExtension implements DynamicMethodThrowTypeExtension
{
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getDeclaringClass()->getName() === 'App\ComponentContainer'
            && $methodReflection->getName() === 'getComponent';
    }

    public function getThrowTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope
    ): ?Type {
        // Method: getComponent(string $name, bool $throw = true)
        if (count($methodCall->getArgs()) < 2) {
            // If $throw is not passed, the default is true -> exception is thrown
            return $methodReflection->getThrowType();
        }

        $argType = $scope->getType($methodCall->getArgs()[1]->value);
        // If the second argument is `true`, the exception is thrown
        if ((new ConstantBooleanType(true))->isSuperTypeOf($argType)->yes()) {
            return $methodReflection->getThrowType();
        }

        // Otherwise (e.g., with `false`), no exception is thrown
        return null;
    }
}
```

### Example: IgnoreErrorExtension

Sometimes it makes sense to ignore errors based on dynamic logic rather than configuration.

```php
<?php declare(strict_types=1);

namespace App\PHPStan\Extensions;

use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PhpParser\Node;
use PHPStan\Node\InClassMethodNode;

// Ignores "missingType.iterableValue" errors in public Action methods of controllers
class ControllerActionReturnTypeIgnoreExtension implements IgnoreErrorExtension
{
    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if ($error->getIdentifier() !== 'missingType.iterableValue') {
            return false;
        }

        if (!$node instanceof InClassMethodNode) {
            return false;
        }

        if (!str_ends_with($node->getClassReflection()->getName(), 'Controller')) {
            return false;
        }

        if (!str_ends_with($node->getMethodReflection()->getName(), 'Action')) {
            return false;
        }

        return $node->getMethodReflection()->isPublic();
    }
}
```

**Registration in `phpstan.neon`:**

```yaml
services:
    -   class: App\PHPStan\Extensions\ControllerActionReturnTypeIgnoreExtension
        tags:
            - phpstan.ignoreErrorExtension
```

### Other Useful, Advanced Extensions

-   **`TypeNodeResolverExtension`**: Implement this extension to teach PHPStan how to handle custom PHPDoc tags (e.g., `@psalm-import-type` or custom `@custom-type` definitions).
-   **`ResultCacheMetaExtension`**: If your extension depends on external files (e.g., an XML configuration), use this extension. It provides a hash of the external file's content. If the hash changes, PHPStan invalidates its cache.
-   **`ClassDeprecationExtension`**: Allows you to implement custom deprecation logic that goes beyond the `@deprecated` tag, e.g., by checking for custom `#[Deprecated]` attributes.

### Providing Magic Methods & Properties

For "magic" APIs (e.g., ActiveRecord-like ORMs), you can tell PHPStan which methods/properties exist:

```php
<?php declare(strict_types=1);

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\Php\PhpMethodReflectionFactory;

final class MagicFindByExtension implements MethodsClassReflectionExtension
{
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return str_starts_with($methodName, 'findBy');
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        // ... create a suitable MethodReflection here,
        // e.g., via PhpMethodReflectionFactory or a custom implementation
    }
}
```

Registration:

```neon
services:
    - class: App\PHPStan\Extensions\MagicFindByExtension
      tags: [phpstan.broker.methodsClassReflectionExtension]
```

### Using ReflectionProvider Effectively

Many tasks can be solved cleanly using the container service `PHPStan\Reflection\ReflectionProvider`:

```php
public function __construct(private \PHPStan\Reflection\ReflectionProvider $rp) {}

public function processNode(Node $node, Scope $scope): array
{
    if ($node instanceof \PhpParser\Node\Name) {
        $fqn = (string) $node; // fully qualified name, if already resolved
        if ($this->rp->hasClass($fqn)) {
            $class = $this->rp->getClass($fqn);
            // e.g., inspect parent classes, interfaces, traits
            $parents = array_map(static fn($r) => $r->getName(), $class->getParents());
        }
    }
    return [];
}
```

## Working with the Type System (Practical Snippets)

These snippets save time when implementing precise checks.

```php
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VerbosityLevel;

// 1) Extract constant strings (e.g., for factory methods)
foreach ($type->getConstantStrings() as $const) {
    $value = $const->getValue();
}

// 2) Check and add nullability
if (TypeCombinator::containsNull($type)) { /* … */ }
$nullable = TypeCombinator::addNull($type);

// 3) Readable type name for error messages
$pretty = $type->describe(VerbosityLevel::value());

// 4) Check array shape
foreach (TypeUtils::getConstantArrays($type) as $constArray) {
    // Inspect keys/values
}
```

## Stability of Error Messages & Identifiers

- Choose unique identifiers (`vendor.feature.ruleName`) and maintain them.
- Keep message texts stable (do not render volatile details like a dynamic list of all findings inline).
- For existing baselines: Only change identifiers if absolutely necessary; otherwise, baseline churn.

## Testing Extensions Beyond RuleTestCase

While `RuleTestCase` is perfect for most `Rule` implementations, PHPStan offers a specialized test class for extensions that affect the type system.

### `TypeInferenceTestCase` for Type Extensions

For `Dynamic*ReturnTypeExtension` or `*TypeSpecifyingExtension`, `TypeInferenceTestCase` is the right choice. Instead of checking for errors, this test type compares the inferred types in the code with expected types.

**Procedure:**

1.  **Create a fixture file with `assertType`:** You create a PHP file containing the code to be tested. At the points where you want to check a type, you add the call `\PHPStan\Testing\assertType('Expected\\Type', $variable);`.
2.  **Implement the test class:**

```php
<?php declare(strict_types=1);

namespace Tests\PHPStan\Extensions;

use PHPStan\Testing\TypeInferenceTestCase;

class MyContainerDynamicReturnTypeExtensionTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        // Path to the fixture file with the assertType calls
        yield from self::gatherAssertTypes(__DIR__ . '/fixtures/my-container-types.php');
    }

    /**
     * @dataProvider dataFileAsserts
     */
    public function testFileAsserts(
        string $assertType,
        string $file,
        mixed ...$args
    ): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public static function getAdditionalConfigFiles(): array
    {
        // Path to the extension.neon that registers the extension to be tested
        return [__DIR__ . '/../../extension.neon'];
    }
}
```

This approach ensures that your type extensions work correctly and that PHPStan derives the correct types from your code.

## Performance Fine-Tuning in Large Codebases

- Choose specific node types (`ClassMethod` instead of `Node`).
- Use early returns consistently, especially for trivially-false paths.
- Execute expensive checks (reflections, filesystem) only when necessary and cache results locally.
- If data is needed across files: use a `Collector` and perform the evaluation in a separate rule.

## Migration and Adoption Strategy

- Start in "advisory mode": The rule provides `->tip()` and soft hints without `nonIgnorable()`.
- Then, publish identifiers and show `ignoreErrors` examples in the team documentation.
- Only switch to a stricter variant once stability is proven (possibly `nonIgnorable()` for hard architectural rules).

## Common Practical Cases as a Starting Point

-   Prohibition of certain framework facades/static calls: Rule checks `PhpParser\Node\Expr\StaticCall` and the target class/method.
-   Restriction of direct `new` instantiations: Rule checks `PhpParser\Node\Expr\New_` and whitelists allowed factories.
-   Enforcement of Value Objects instead of primitive arrays: Rule checks parameter and return types for `ConstantArrayType` or `ArrayType` and provides hints.

## Alternative Extension Types for Specific Use Cases

### Using Node Visitors for Complex Analysis
For rules requiring traversal of nested structures, implement a NodeVisitor to collect information during AST traversal. Extend `PhpParser\NodeVisitorAbstract` and use it within your rule's processNode method.

```php
use PhpParser\NodeVisitorAbstract;
use PhpParser\NodeTraverser;

class MyVisitor extends NodeVisitorAbstract {
    // Implement enterNode/leaveNode
}

public function processNode(Node $node, Scope $scope): array {
    $traverser = new NodeTraverser();
    $visitor = new MyVisitor();
    $traverser->addVisitor($visitor);
    $traverser->traverse([$node]);
    // Analyze collected data
}
```

## Alternative Extension Types for Specific Use Cases

Not every problem requires a complex class derived from `PHPStan\Rules\Rule`. PHPStan offers specialized extension interfaces that are simpler and more performant for certain use cases.

### Restricting Code Usage with `Restricted*UsageExtension`

If you simply want to forbid or conditionally allow the use of certain classes, methods, or properties, this family of extensions is often a better choice than a custom rule.

**Example: Forbidding the use of a specific method outside of test code**

```php
<?php declare(strict_types=1);

namespace App\PHPStan\Extensions;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\RestrictedUsage\RestrictedUsage;
use PHPStan\Rules\RestrictedUsage\RestrictedMethodUsageExtension;

class ForbidTestingUtilsInSrcExtension implements RestrictedMethodUsageExtension
{
    public function isRestrictedMethodUsage(
        ExtendedMethodReflection $methodReflection,
        Scope $scope
    ): ?RestrictedUsage {
        // Check if the called method is from the Testing namespace
        if (str_starts_with($methodReflection->getDeclaringClass()->getName(), 'App\Testing\')) {
            // Check if the call is *not* from the Tests namespace
            if (!$scope->isInNamespace('Tests\\')) {
                return RestrictedUsage::create(
                    sprintf(
                        'Cannot call test utility method %s::%s() from application code.',
                        $methodReflection->getDeclaringClass()->getName(),
                        $methodReflection->getName()
                    ),
                    'testing.usageInSrc'
                );
            }
        }

        return null;
    }
}
```

**Registration in `phpstan.neon`:**

```yaml
services:
    -   class: App\PHPStan\Extensions\ForbidTestingUtilsInSrcExtension
        tags:
            - phpstan.restrictedMethodUsageExtension
```

There are other interfaces like `RestrictedPropertyUsageExtension` and `RestrictedFunctionUsageExtension` that work on the same principle.

### Enforcing Architecture with `AllowedSubTypesClassReflectionExtension`

This extension allows you to define a whitelist of classes that are allowed to extend or implement a specific class or interface. This is a powerful tool for enforcing architectural rules.

**Example: Only specific classes may implement a `PaymentHandlerInterface`.**

```php
<?php declare(strict_types=1);

namespace App\PHPStan\Extensions;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\AllowedSubTypesClassReflectionExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class PaymentHandlerSubtypeExtension implements AllowedSubTypesClassReflectionExtension
{
    public function supports(ClassReflection $classReflection): bool
    {
        return $classReflection->getName() === 'App\Payments\PaymentHandlerInterface';
    }

    /** @return array<Type> */
    public function getAllowedSubTypes(ClassReflection $classReflection): array
    {
        return [
            new ObjectType('App\Payments\CreditCardHandler'),
            new ObjectType('App\Payments\PayPalHandler'),
        ];
    }
}
```

**Registration in `phpstan.neon`:**

```yaml
services:
    -   class: App\PHPStan\Extensions\PaymentHandlerSubtypeExtension
        tags:
            - phpstan.broker.allowedSubTypesClassReflectionExtension
```

## Common AST Structure Problems and Solutions

### Problem: Node attributes do not work in PHPStan 1.6.0+

**Symptom:** Rules using `$node->getAttribute('previous')`, `getAttribute('parent')`, or `getAttribute('next')` no longer work reliably.

**Cause:** PHPStan 1.6.0+ with the Bleeding Edge feature breaks reference cycles between AST nodes to avoid memory leaks. The `NodeConnectingVisitor` attributes are therefore no longer available.

**Solution:** Switch from statement-level to function-level analysis:

```php
// WRONG - no longer works reliably
public function getNodeType(): string {
    return Return_::class;
}

public function processNode(Node $node, Scope $scope): array {
    $prevStatement = $node->getAttribute('previous'); // null in PHPStan 1.6.0+
    // ...
}

// RIGHT - modern, robust solution
public function getNodeType(): string {
    return Node::class; // Or Node\Stmt\Function_::class for better performance
}

public function processNode(Node $node, Scope $scope): array {
    if (!$node instanceof Node\Stmt\Function_ && !$node instanceof Node\Stmt\ClassMethod) {
        return [];
    }

    $statements = $node->getStmts();
    if ($statements === null || count($statements) < 2) {
        return [];
    }

    // Consecutive statement analysis
    for ($i = 0; $i < count($statements) - 1; $i++) {
        $current = $statements[$i];
        $next = $statements[$i + 1];
        // Analysis of consecutive statements
    }
}
```

### Problem: throw statements have an unexpected AST structure

**Symptom:** Rules do not detect `throw $variable;` even though they correctly detect `return $variable;`.

**Cause:** `throw` statements have a different AST structure than `return` statements:

- `return $a;` → `Node\Stmt\Return_` containing `Node\Expr\Variable`
- `throw $e;` → `Node\Stmt\Expression` containing `Node\Expr\Throw_` containing `Node\Expr\Variable`

**Solution:** Correct handling of both statement types:

```php
use PhpParser\Node\Expr\Throw_; // IMPORTANT: Expr\Throw_, not Stmt\Throw_

// Correct detection of both types
$nextExpr = null;
if ($next instanceof Return_) {
    $nextExpr = $next->expr;
} elseif ($next instanceof Expression && $next->expr instanceof Throw_) {
    $nextExpr = $next->expr->expr; // Variable inside the throw
} else {
    continue;
}
```

### Debugging Strategies for AST Structure Problems

**Inspecting AST Structure:**

```php
// Temporary debugging in processNode
public function processNode(Node $node, Scope $scope): array {
    // Output node type and structure
    error_log("Node type: " . get_class($node));
    error_log("Node attributes: " . json_encode($node->getAttributes()));
    
    // For statement containers: inspect child nodes
    if ($node instanceof Node\Stmt\Function_) {
        foreach ($node->getStmts() ?? [] as $i => $stmt) {
            error_log("Statement $i: " . get_class($stmt));
            if ($stmt instanceof Expression) {
                error_log("  -> Inner expr: " . get_class($stmt->expr));
            }
        }
    }
}
```

**Using the PHP-Parser CLI:**

```bash
# Dump AST structure for debugging
vendor/bin/php-parse --dump debug-file.php
```

### Performance Optimization for Function-Level Analysis

**Problem:** Function-level analysis can be slower than statement-level.

**Solutions:**

1.  **More Specific Node Types:** `Node\Stmt\Function_::class` instead of `Node::class`
2.  **Early Returns:** Abort unnecessary analyses early
3.  **Statement Type Checks:** Only check relevant statement combinations

```php
public function getNodeType(): string {
    return Node\Stmt\Function_::class; // Specific type for better performance
}

public function processNode(Node $node, Scope $scope): array {
    $statements = $node->getStmts();
    
    // Early return for trivial cases
    if ($statements === null || count($statements) < 2) {
        return [];
    }

    for ($i = 0; $i < count($statements) - 1; $i++) {
        $current = $statements[$i];
        $next = $statements[$i + 1];

        // Early type checks for performance
        if (!$current instanceof Expression || !$current->expr instanceof Assign) {
            continue; // Skip instead of performing expensive analyses
        }

        if (!$next instanceof Return_ && !($next instanceof Expression && $next->expr instanceof Throw_)) {
            continue;
        }

        // Only perform expensive analysis for matching types
        // ...
    }
}
```

## Container-Based Rule Implementation: The Modern PHPStan Approach

### The Parent-Attribute Problem (PHPStan 1.10+)

**CRITICAL KNOWLEDGE:** Since PHPStan 1.10+, parent node attributes are **disabled by default** for memory optimization. This means:

```php
// ❌ NO LONGER WORKS reliably
public function processNode(Node $node, Scope $scope): array {
    $parent = $node->getAttribute('parent'); // null in PHPStan 1.10+
    // Parent traversal fails!
}
```

**Symptoms of the Problem:**
- `getAttribute('parent')` returns `null`
- Parent-based AST traversal fails
- Rules that rely on node position do not work

### The Container-Based Solution

**The modern, recommended approach:** Instead of analyzing bottom-up (from individual nodes upwards), use **Top-Down Container Analysis** (from container nodes downwards).

#### Example: UselessReturnRule - Wrong vs. Right

**❌ Old, faulty implementation:**

```php
/**
 * @implements Rule<Return_>
 */
class UselessReturnRule implements Rule {
    public function getNodeType(): string {
        return Return_::class; // Individual return nodes
    }

    public function processNode(Node $node, Scope $scope): array {
        // Tries parent traversal - fails in PHPStan 1.10+
        $function = $this->getContainingFunction($node); // null!
        return [];
    }

    private function getContainingFunction(Return_ $return): ?Node {
        // ❌ No longer works!
        $current = $return;
        while ($current !== null) {
            $parent = $current->getAttribute('parent'); // null!
            if ($parent instanceof Function_ || $parent instanceof ClassMethod) {
                return $parent;
            }
            $current = $parent;
        }
        return null;
    }
}
```

**✅ Modern, correct implementation:**

```php
/**
 * @implements Rule<Node\FunctionLike>
 */
class UselessReturnRule implements Rule {
    public function getNodeType(): string {
        return Node\FunctionLike::class; // Container nodes: Functions and Methods
    }

    public function processNode(Node $node, Scope $scope): array {
        if (!$node instanceof Node\FunctionLike) {
            return [];
        }

        $errors = [];

        // 1. Senseless return detection: Analysis of the last statements
        $senselessError = $this->checkSenselessReturn($node);
        if ($senselessError !== null) {
            $errors[] = $senselessError;
        }

        // 2. Confusing assignment detection: Search all returns
        $assignmentErrors = $this->checkAllConfusingAssignments($node);
        $errors = array_merge($errors, $assignmentErrors);

        return $errors;
    }

    private function checkSenselessReturn(Node\FunctionLike $function): ?RuleError {
        // Skip abstract methods
        if ($function instanceof ClassMethod && $function->isAbstract()) {
            return null;
        }

        $stmts = $function->getStmts();
        if ($stmts === null || empty($stmts)) {
            return null;
        }

        // Walk backwards to find last non-NOP statement
        for ($i = count($stmts) - 1; $i >= 0; $i--) {
            $candidate = $stmts[$i];
            if ($candidate instanceof Nop) {
                continue; // Skip comment-related NOPs
            }
            
            // Check if last executable statement is bare return;
            if ($candidate instanceof Return_ && $candidate->expr === null) {
                return RuleErrorBuilder::message('Senseless statement: return null implicitly or safely remove it.')
                    ->identifier('return.senseless')
                    ->line($candidate->getStartLine())
                    ->build();
            }
            
            break; // First non-NOP found
        }

        return null;
    }

    private function checkAllConfusingAssignments(Node\FunctionLike $function): array {
        $errors = [];
        $stmts = $function->getStmts();
        
        if ($stmts === null) {
            return $errors;
        }

        // Find all return statements using NodeFinder
        $nodeFinder = new NodeFinder();
        $returnStatements = $nodeFinder->findInstanceOf($stmts, Return_::class);

        foreach ($returnStatements as $return) {
            $error = $this->checkConfusingAssignment($return, $function);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private function checkConfusingAssignment(Return_ $return, Node\FunctionLike $function): ?RuleError {
        if ($return->expr === null || !$return->expr instanceof Assign) {
            return null;
        }

        $assignment = $return->expr;
        $variable = $assignment->var;
        $value = $assignment->expr;

        if (!$variable instanceof Variable || !is_string($variable->name)) {
            return null;
        }

        // Now we can use the containing function!
        if (!$this->isVariableTarget($variable->name, $function)) {
            return null;
        }

        $replacement = 'return ' . $this->getExpressionText($value) . ';';

        return RuleErrorBuilder::message('Assignment here is not making much sense. Replace with: ' . $replacement)
            ->identifier('return.confusingAssignment')
            ->line($return->getStartLine())
            ->build();
    }

    private function isVariableTarget(string $variableName, Node\FunctionLike $function): bool {
        // Now possible with access to the function:
        // - Parameter reference checks
        // - Static variable checks
        // - Finally block usage checks
        return !$this->isArgumentReference($variableName, $function)
            && !$this->isStaticVariable($variableName, $function)
            && !$this->isUsedInFinally($variableName, $function);
    }

    // Helper methods with Node\FunctionLike parameter...
}
```

### Why Container-Based is Better

**1. Reliability:**
- No dependency on parent attributes
- Works in all PHPStan versions
- Memory-efficient

**2. Performance:**
- Fewer traversals
- Direct access to statement arrays
- Better caching possibilities

**3. Complete Context:**
- Access to all function/method information
- Complete statement list available
- Easier implementation of complex logic

### Using the Node\FunctionLike Interface

**Best Practice:** Use `Node\FunctionLike` for rules that need to analyze both functions and methods:

```php
// ✅ Unified approach for functions and methods
public function getNodeType(): string {
    return Node\FunctionLike::class;
}

// ✅ Both types are covered automatically
public function processNode(Node $node, Scope $scope): array {
    if (!$node instanceof Node\FunctionLike) {
        return [];
    }
    
    // $node can be Function_ or ClassMethod
    $statements = $node->getStmts();
    $parameters = $node->getParams();
    // ...
}
```

### Using NodeFinder for Complex Searches

**For searches within container nodes:**

```php
use PHPStan\NodeFinder;

private function findAllReturns(Node\FunctionLike $function): array {
    $nodeFinder = new NodeFinder();
    return $nodeFinder->findInstanceOf($function->getStmts() ?? [], Return_::class);
}

private function findVariableUsages(Node\FunctionLike $function, string $variableName): array {
    $nodeFinder = new NodeFinder();
    return $nodeFinder->find($function->getStmts() ?? [], function (Node $node) use ($variableName) {
        return $node instanceof Variable && $node->name === $variableName;
    });
}
```

### Backward Iteration for "Last Statement" Detection

**Pattern for "last statement" detection:**

```php
private function getLastExecutableStatement(array $statements): ?Node {
    // Walk backwards, skip NOPs (comments)
    for ($i = count($statements) - 1; $i >= 0; $i--) {
        $candidate = $statements[$i];
        if ($candidate instanceof Nop) {
            continue; // Skip comment-related statements
        }
        return $candidate; // First non-NOP found
    }
    return null;
}
```

### Testing Container-Based Rules

**Test patterns for container-based rules:**

```php
/**
 * @extends RuleTestCase<UselessReturnRule>
 */
class UselessReturnRuleTest extends RuleTestCase {
    protected function getRule(): Rule {
        return new UselessReturnRule();
    }

    public function testSenselessReturnDetection(): void {
        $this->analyse([__DIR__ . '/fixtures/senseless-returns.php'], [
            [
                'Senseless statement: return null implicitly or safely remove it.',
                12, // line number where function ends with bare return;
            ],
            [
                'Senseless statement: return null implicitly or safely remove it.',
                25, // another function with senseless return
            ],
        ]);
    }

    public function testEarlyReturnsNotFlagged(): void {
        $this->analyse([__DIR__ . '/fixtures/early-returns.php'], [
            // No errors expected - early returns should not be flagged
        ]);
    }
}
```

**Fixture Examples:**

```php
<?php
// fixtures/senseless-returns.php

function senselessReturn(): void {
    echo "Hello";
    return; // Should be flagged - line 12
}

function earlyReturn(): void {
    if (true) {
        return; // Should NOT be flagged - early return
    }
    echo "This won\'t execute";
}

function normalFunction(): void {
    echo "Normal function";
    // No explicit return - should NOT be flagged
}
```

### Migrating Old Rules

**Step-by-step migration:**

1.  **Change Node Type:** `Return_::class` → `Node\FunctionLike::class`
2.  **Rewrite processNode:** Implement container analysis
3.  **Remove Parent Traversal:** Replace with direct statement analysis
4.  **Use NodeFinder:** For complex node searches
5.  **Adapt Tests:** Use new test patterns

### Key Takeaways

-   **PHPStan 1.10+ has disabled parent attributes** - old rules no longer work
-   **Container-Based is the modern standard** - recommended by PHPStan developers
-   **Node\FunctionLike unifies** functions and methods
-   **Performance is better** due to fewer traversals
-   **More context is available** for more precise analyses

This modern approach makes PHPStan rules more robust, performant, and future-proof.
