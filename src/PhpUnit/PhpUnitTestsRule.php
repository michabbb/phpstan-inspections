<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\PhpUnit;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects PHPUnit-related bugs and suggests best practices.
 *
 * This rule analyzes PHPUnit test code and identifies several issues:
 * - @dataProvider annotations referencing non-existing methods
 * - @dataProvider methods that could use named datasets for better maintainability
 * - @depends annotations referencing non-existing or inappropriate methods
 * - @covers annotations referencing non-existing classes or methods
 * - @test annotations used on methods that already start with 'test' (ambiguous)
 *
 * The rule helps maintain clean and correct PHPUnit test suites by catching
 * common mistakes and suggesting improvements for better test code quality.
 *
 * @implements Rule<ClassMethod>
 */
final class PhpUnitTestsRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        $errors = [];

        // Get method information
        $methodName = $node->name->toString();
        $docComment = $node->getDocComment();

        if ($docComment === null) {
            return [];
        }

        // Parse PHPDoc for PHPUnit annotations
        $errors = array_merge($errors, $this->checkDataProviderAnnotations($node, $scope, $docComment));
        $errors = array_merge($errors, $this->checkDependsAnnotations($node, $scope, $docComment));
        $errors = array_merge($errors, $this->checkCoversAnnotations($node, $scope, $docComment));
        $errors = array_merge($errors, $this->checkTestAnnotationAmbiguity($node, $methodName, $docComment));

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function checkDataProviderAnnotations(ClassMethod $node, Scope $scope, \PhpParser\Comment\Doc $docComment): array
    {
        $errors = [];

        // Extract @dataProvider annotations
        $dataProviderTags = $this->extractDocTags($docComment, '@dataProvider');

        foreach ($dataProviderTags as $tag) {
            $providerMethodName = $this->extractTagValue($tag);

            if ($providerMethodName === null) {
                continue;
            }

            // Check if provider method exists
            if (!$this->methodExistsInClass($scope, $providerMethodName)) {
                $errors[] = RuleErrorBuilder::message(
                    "@dataProvider referencing to a non-existing entity."
                )
                    ->identifier('phpunit.dataProvider.nonExisting')
                    ->line($node->getStartLine())
                    ->build();
                continue;
            }

            // Check if we can suggest named datasets
            $suggestion = $this->checkNamedDatasetsSuggestion($scope, $providerMethodName);
            if ($suggestion !== null) {
                $errors[] = RuleErrorBuilder::message($suggestion)
                    ->identifier('phpunit.dataProvider.namedDatasets')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function checkDependsAnnotations(ClassMethod $node, Scope $scope, \PhpParser\Comment\Doc $docComment): array
    {
        $errors = [];

        // Extract @depends annotations
        $dependsTags = $this->extractDocTags($docComment, '@depends');

        foreach ($dependsTags as $tag) {
            $dependencyMethodName = $this->extractTagValue($tag);

            if ($dependencyMethodName === null) {
                continue;
            }

            // Check if dependency method exists
            if (!$this->methodExistsInClass($scope, $dependencyMethodName)) {
                $errors[] = RuleErrorBuilder::message(
                    "@depends referencing to a non-existing or inappropriate entity."
                )
                    ->identifier('phpunit.depends.nonExisting')
                    ->line($node->getStartLine())
                    ->build();
                continue;
            }

            // Check if dependency method is appropriate (should start with 'test' or have @test annotation)
            if (!$this->isAppropriateTestMethod($scope, $dependencyMethodName)) {
                $errors[] = RuleErrorBuilder::message(
                    "@depends referencing to a non-existing or inappropriate entity."
                )
                    ->identifier('phpunit.depends.inappropriate')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function checkCoversAnnotations(ClassMethod $node, Scope $scope, \PhpParser\Comment\Doc $docComment): array
    {
        $errors = [];

        // Extract @covers annotations
        $coversTags = $this->extractDocTags($docComment, '@covers');

        foreach ($coversTags as $tag) {
            $coversValue = $this->extractTagValue($tag);

            if ($coversValue === null) {
                continue;
            }

            // Check if the covered entity exists
            if (!$this->coveredEntityExists($scope, $coversValue)) {
                $errors[] = RuleErrorBuilder::message(
                    "@covers referencing to a non-existing entity '{$coversValue}'"
                )
                    ->identifier('phpunit.covers.nonExisting')
                    ->line($node->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function checkTestAnnotationAmbiguity(ClassMethod $node, string $methodName, \PhpParser\Comment\Doc $docComment): array
    {
        $errors = [];

        // Check if method name starts with 'test'
        $startsWithTest = str_starts_with($methodName, 'test');

        // Check if @test annotation is present
        $testTags = $this->extractDocTags($docComment, '@test');

        if ($startsWithTest && !empty($testTags)) {
            $errors[] = RuleErrorBuilder::message(
                "@test is ambiguous because method name starts with 'test'."
            )
                ->identifier('phpunit.test.ambiguous')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function extractDocTags(\PhpParser\Comment\Doc $docComment, string $tagName): array
    {
        $tags = [];
        $lines = explode("\n", $docComment->getText());

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '*' . $tagName) || str_contains($line, $tagName)) {
                $tags[] = $line;
            }
        }

        return $tags;
    }

    private function extractTagValue(string $tagLine): ?string
    {
        // Simple extraction - look for content after the tag
        $parts = explode(' ', $tagLine, 2);
        return $parts[1] ?? null;
    }

    private function methodExistsInClass(Scope $scope, string $methodName): bool
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return false;
        }

        return $classReflection->hasMethod($methodName);
    }

    private function checkNamedDatasetsSuggestion(Scope $scope, string $methodName): ?string
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || !$classReflection->hasMethod($methodName)) {
            return null;
        }

        $methodReflection = $classReflection->getMethod($methodName, $scope);

        // This is a simplified check - in practice, we'd need to analyze the method body
        // For now, we'll suggest named datasets for any data provider method
        return "It would be better for maintainability to use named datasets in @dataProvider.";
    }

    private function isAppropriateTestMethod(Scope $scope, string $methodName): bool
    {
        // Method should start with 'test' or have @test annotation
        if (str_starts_with($methodName, 'test')) {
            return true;
        }

        // Check for @test annotation (simplified - would need proper doc parsing)
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || !$classReflection->hasMethod($methodName)) {
            return false;
        }

        $methodReflection = $classReflection->getMethod($methodName, $scope);
        $docComment = $methodReflection->getDocComment();

        return $docComment !== null && str_contains($docComment, '@test');
    }

    private function coveredEntityExists(Scope $scope, string $coversValue): bool
    {
        // Remove leading backslash if present
        $coversValue = ltrim($coversValue, '\\');

        // Check if it's a class
        if ($this->reflectionProvider->hasClass($coversValue)) {
            return true;
        }

        // Check if it's a method (Class::method format)
        if (str_contains($coversValue, '::')) {
            [$className, $methodName] = explode('::', $coversValue, 2);

            if ($this->reflectionProvider->hasClass($className)) {
                $classReflection = $this->reflectionProvider->getClass($className);
                return $classReflection->hasMethod($methodName);
            }
        }

        return false;
    }
}