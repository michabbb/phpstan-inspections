<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects deprecated PHP 4-style constructors.
 *
 * This rule identifies classes that use PHP 4-style constructors (methods with the same name as the class)
 * instead of the modern __construct() method. PHP 4-style constructors are deprecated and should be
 * replaced with __construct() for better code maintainability and compatibility.
 *
 * @implements Rule<Node\Stmt>
 */
final class DeprecatedConstructorStyleRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Skip traits and interfaces as per Java inspector
        if ($node instanceof Trait_ || $node instanceof Interface_) {
            return [];
        }

        if (!$node instanceof Class_) {
            return [];
        }

        $className = (string) $node->name;
        $errors = [];
        $hasModernConstructor = false;

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                if ($stmt->name->toString() === '__construct') {
                    $hasModernConstructor = true;
                    break;
                }
            }
        }

        if ($hasModernConstructor) {
            return []; // Modern constructor exists, no deprecated style
        }

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                // Check for non-static method with the same name as the class
                if (!$stmt->isStatic() && $stmt->name->toString() === $className) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf("'%s' has a deprecated constructor.", $className)
                    )
                    ->identifier('class.deprecatedConstructor')
                    ->line($stmt->getStartLine())
                    ->build();
                }
            }
        }

        return $errors;
    }
}
