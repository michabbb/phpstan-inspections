<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects missing __isset() method in classes implementing ArrayAccess.
 *
 * This rule checks if classes that implement the ArrayAccess interface also implement
 * the __isset() magic method. The __isset() method is crucial for proper isset() and
 * empty() behavior on array-like objects, ensuring correct property existence checking.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Stmt\Class_>
 */
final class MissingIssetImplementationRule implements Rule
{

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        // Skip abstract classes and interfaces using AST
        if ($node->isAbstract() || $node instanceof \PhpParser\Node\Stmt\Interface_) {
            return [];
        }

        // Check if the class implements ArrayAccess by examining the AST
        $implementsArrayAccess = false;
        if ($node->implements !== null) {
            foreach ($node->implements as $interface) {
                $interfaceName = (string) $interface;
                if ($interfaceName === 'ArrayAccess' || $interfaceName === '\ArrayAccess') {
                    $implementsArrayAccess = true;
                    break;
                }
            }
        }

        if (!$implementsArrayAccess) {
            return [];
        }

        // Check if __isset() method is implemented 
        // Check directly in the AST first
        $hasIssetInClass = false;
        if ($node->stmts !== null) {
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof \PhpParser\Node\Stmt\ClassMethod && $stmt->name->toString() === '__isset') {
                    $hasIssetInClass = true;
                    break;
                }
            }
        }

        if (!$hasIssetInClass) {
            return [
                RuleErrorBuilder::message('Missing __isset() implementation for ArrayAccess')
                    ->identifier('arrayAccess.missingIsset')
                    ->tip('Classes implementing ArrayAccess should also implement __isset() method for proper isset() and empty() behavior')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
