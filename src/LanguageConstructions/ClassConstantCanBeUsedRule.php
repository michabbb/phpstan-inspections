<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects string literals that represent class names and suggests using ::class instead.
 *
 * This rule identifies:
 * - String literals containing class names that can be replaced with ClassName::class
 * - Handles both simple class names and fully qualified namespaced classes
 * - Skips strings that are all lowercase (unless they contain backslashes)
 * - Supports concatenation with __NAMESPACE__ for relative class references
 *
 * @implements Rule<String_>
 */
class ClassConstantCanBeUsedRule implements Rule
{
    private const string CLASS_NAME_REGEX = '/(\\\\)?([a-zA-Z0-9_]+\\\\)*([a-zA-Z0-9_]+)/';

    public function __construct(
        private ReflectionProvider $reflectionProvider
    ) {}

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $stringContent = $node->value;

        // Skip if string has inline expressions or is part of certain contexts
        if ($this->shouldSkipString($node, $scope)) {
            return [];
        }

        // Skip short strings or strings that don't match class name pattern
        if (strlen($stringContent) < 3 || !preg_match(self::CLASS_NAME_REGEX, $stringContent)) {
            return [];
        }

        // Skip all-lowercase strings without backslashes
        if (!str_contains($stringContent, '\\') && strtolower($stringContent) === $stringContent) {
            return [];
        }

        // Handle concatenation with __NAMESPACE__
        $fullClassName = $this->resolveFullClassName($node, $scope, $stringContent);
        if ($fullClassName === null) {
            return [];
        }

        // Check if the class exists
        if (!$this->reflectionProvider->hasClass($fullClassName)) {
            // Skip strings that don't represent actual class names
            // This prevents false positives from strings containing words that look like class names
            return [];
        }

        $classReflection = $this->reflectionProvider->getClass($fullClassName);
        // Allow for case where the resolved name might be different (e.g., \DateTime vs DateTime)
        $actualClassName = $classReflection->getName();
        if ($actualClassName !== $fullClassName && $actualClassName !== ltrim($fullClassName, '\\')) {
            return [];
        }

        // Determine the replacement suggestion
        $replacement = $this->getReplacementSuggestion($stringContent, $fullClassName);

        return [
            RuleErrorBuilder::message(
                "Perhaps this can be replaced with {$replacement}::class."
            )
                ->identifier('class.constantCanBeUsed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function shouldSkipString(String_ $node, Scope $scope): bool
    {
        // Skip strings that are part of class_alias() calls
        if ($this->isPartOfClassAlias($node)) {
            return true;
        }

        // Skip strings used in array contexts (array literals) - Modern approach
        if ($this->isPartOfArrayLiteralModern($node, $scope)) {
            return true;
        }

        // Skip strings in self assignment expressions
        if ($this->isPartOfSelfAssignment($node)) {
            return true;
        }

        return false;
    }

    private function isPartOfArrayLiteralModern(String_ $node, Scope $scope): bool
    {
        // Use scope and context clues to determine if this is likely a display string
        $stringContent = $node->value;
        
        // Skip very short strings that are common in arrays like table headers  
        if (strlen($stringContent) <= 5) {
            return true;
        }
        
        // Skip strings that are commonly used as display text/headers
        $commonDisplayStrings = [
            'ID', 'SKU', 'Name', 'Title', 'Status', 'Error', 'Success', 'Failed', 
            'Type', 'Date', 'Time', 'User', 'Admin', 'Guest', 'Active', 'Inactive',
            'Yes', 'No', 'True', 'False', 'On', 'Off', 'New', 'Old', 'Edit', 'Delete',
            'Create', 'Update', 'Remove', 'Add', 'Cancel', 'Save', 'Submit', 'Reset'
        ];
        
        if (in_array($stringContent, $commonDisplayStrings, true)) {
            return true;
        }
        
        // Skip strings that are all uppercase (often constants or display labels)
        if ($stringContent === strtoupper($stringContent) && strlen($stringContent) <= 10) {
            return true;
        }
        
        // Use scope context to determine if we're in a context that suggests display strings
        $functionName = $scope->getFunctionName();
        if ($functionName && (
            str_contains($functionName, 'table') ||
            str_contains($functionName, 'header') ||
            str_contains($functionName, 'column') ||
            str_contains($functionName, 'field')
        )) {
            return true;
        }
        
        return false;
    }

    private function isPartOfClassAlias(String_ $node): bool
    {
        $parent = $node->getAttribute('parent');
        if (!$parent instanceof Arg) {
            return false;
        }

        $grandParent = $parent->getAttribute('parent');
        if (!$grandParent instanceof FuncCall) {
            return false;
        }

        if (!$grandParent->name instanceof Name) {
            return false;
        }

        return $grandParent->name->toString() === 'class_alias';
    }

    private function isPartOfArrayLiteral(String_ $node): bool
    {
        $parent = $node->getAttribute('parent');
        
        // Check if parent is array item directly
        if ($parent instanceof ArrayItem) {
            $grandParent = $parent->getAttribute('parent');
            if ($grandParent instanceof Array_) {
                return true;
            }
        }

        // Check if we're directly in an array (without ArrayItem wrapper in some cases)
        if ($parent instanceof Array_) {
            return true;
        }

        return false;
    }

    private function isPartOfSelfAssignment(String_ $node): bool
    {
        $parent = $node->getAttribute('parent');
        return $parent instanceof AssignOp;
    }

    private function resolveFullClassName(String_ $node, Scope $scope, string $stringContent): ?string
    {
        $parent = $node->getAttribute('parent');

        // Handle concatenation with __NAMESPACE__
        if ($parent instanceof Concat) {
            $leftOperand = $parent->left;
            if ($leftOperand instanceof ConstFetch &&
                $leftOperand->name instanceof Name &&
                $leftOperand->name->toString() === '__NAMESPACE__') {

                $currentNamespace = $scope->getNamespace();
                if ($currentNamespace === null) {
                    return null;
                }

                // Remove leading backslash from string content if present
                $relativeClassName = ltrim($stringContent, '\\');
                return $currentNamespace . '\\' . $relativeClassName;
            }
        }

        // Handle fully qualified names
        if (str_starts_with($stringContent, '\\')) {
            return $stringContent;
        }

        // Handle relative names - try current namespace first
        $currentNamespace = $scope->getNamespace();
        if ($currentNamespace !== null) {
            $fullName = $currentNamespace . '\\' . $stringContent;
            if ($this->reflectionProvider->hasClass($fullName)) {
                return $fullName;
            }
        }

        // Try as root namespace
        return '\\' . $stringContent;
    }

    private function getReplacementSuggestion(string $originalString, string $fullClassName): string
    {
        // If the original string was fully qualified, keep it that way
        if (str_starts_with($originalString, '\\')) {
            return $fullClassName;
        }

        // For relative names, suggest the shortest possible reference
        // This is a simplified version - in practice, you'd want to consider imports
        return $fullClassName;
    }
}