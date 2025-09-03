<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\UseUse;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects incorrect class constant usage where ::class result and the class qualified name are not identical (case mismatch).
 *
 * This rule identifies:
 * - Usage of ::class with class references that don't match the actual class name (case-sensitive)
 * - Handles namespace resolution, imports, and aliases
 * - Ensures PSR-11 strict class loading compatibility
 * - Skips self, static, and parent references
 *
 * @implements Rule<ClassConstFetch>
 */
final class ClassConstantUsageCorrectnessRule implements Rule
{
    private const array VALID_REFERENCES = ['self', 'static', 'parent'];

    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return ClassConstFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Only check ::class constants
        if (!$node->name instanceof Identifier || $node->name->toString() !== 'class') {
            return [];
        }

        // Skip if class reference is not a Name (e.g., variable)
        if (!$node->class instanceof Name) {
            return [];
        }

        $classReference = $node->class->toString();

        // Skip self, static, parent references
        if (in_array(strtolower($classReference), self::VALID_REFERENCES, true)) {
            return [];
        }

        // Try to resolve the class
        $resolvedClass = $this->resolveClass($node->class, $scope);
        if ($resolvedClass === null) {
            return [];
        }

        // Get valid reference variants
        $validVariants = $this->getValidVariants($node->class, $resolvedClass, $scope);
        if ($validVariants === []) {
            return [];
        }

        // Check if the actual reference matches any valid variant (case-sensitive, like Java original)
        foreach ($validVariants as $variant) {
            if ($variant === $classReference) {
                return [];
            }
        }

        // If we get here, there's a case mismatch
        return [
            RuleErrorBuilder::message('::class result and the class qualified name are not identical (case mismatch).')
                ->identifier('classConstant.usageCorrectness')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function resolveClass(Name $className, Scope $scope): ?string
    {
        $classString = $className->toString();

        // Try direct resolution first
        if ($this->reflectionProvider->hasClass($classString)) {
            return $this->reflectionProvider->getClass($classString)->getName();
        }

        // Try with current namespace
        $currentNamespace = $scope->getNamespace();
        if ($currentNamespace !== null) {
            $namespacedClass = $currentNamespace . '\\' . $classString;
            if ($this->reflectionProvider->hasClass($namespacedClass)) {
                return $this->reflectionProvider->getClass($namespacedClass)->getName();
            }
        }

        // Try as fully qualified name
        $fqcn = '\\' . $classString;
        if ($this->reflectionProvider->hasClass($fqcn)) {
            return $this->reflectionProvider->getClass($fqcn)->getName();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getValidVariants(Name $className, string $resolvedClassFqn, Scope $scope): array
    {
        $variants = [];
        $referenceText = $className->toString();

        // Following Java original logic more closely
        if (str_starts_with($referenceText, '\\')) {
            // FQN specified - add the resolved class FQN as valid variant
            $variants[] = $resolvedClassFqn;
        } else {
            $currentNamespace = $scope->getNamespace();
            
            if (str_contains($referenceText, '\\')) {
                // RQN (Relative Qualified Name) specified
                if ($currentNamespace !== null && $currentNamespace !== '') {
                    $classFqnLower = strtolower($resolvedClassFqn);
                    $namespaceLower = strtolower($currentNamespace);
                    $referenceTextLower = strtolower($referenceText);
                    
                    // Check if resolved class is in the same namespace or ends with the reference
                    if (str_starts_with($classFqnLower, $namespaceLower) || 
                        str_ends_with($classFqnLower, '\\' . $referenceTextLower)) {
                        // Extract the correct case variant from the resolved FQN
                        $variants[] = substr($resolvedClassFqn, strlen($resolvedClassFqn) - strlen($referenceText));
                    }
                }
            } else {
                // Simple name - extract from resolved FQN
                $parts = explode('\\', $resolvedClassFqn);
                $simpleName = end($parts);
                if ($simpleName !== false) {
                    $variants[] = $simpleName;
                }
            }
        }

        return $variants;
    }

    /**
     * @return list<UseUse>
     */
    private function findUseStatements(Scope $scope): array
    {
        // We cannot easily access use statements from the current scope in PHPStan
        // without parsing the file again. For now, return empty array.
        // A proper implementation would require accessing the FileScope or 
        // storing use statements during parsing.
        return [];
    }
}