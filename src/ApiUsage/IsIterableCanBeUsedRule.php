<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests using is_iterable() instead of is_array() || instanceof Traversable pattern.
 *
 * This rule detects when code checks if a variable is an array or implements the Traversable
 * interface using separate conditions, and suggests using the more concise is_iterable()
 * function which was introduced in PHP 7.1 for this exact purpose.
 *
 * @implements Rule<BooleanOr>
 */
class IsIterableCanBeUsedRule implements Rule
{
    private Standard $prettyPrinter;

    public function __construct()
    {
        $this->prettyPrinter = new Standard();
    }

    public function getNodeType(): string
    {
        return BooleanOr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $fragments = $this->extractOrFragments($node);

        $isArrayCall = null;
        $instanceOfTraversable = null;
        $argumentVariable = null;
        $errors = [];

        foreach ($fragments as $fragment) {
            // Look for is_array() call
            if ($fragment instanceof FuncCall && 
                $fragment->name instanceof Name && 
                $fragment->name->toString() === 'is_array') {
                
                $args = $fragment->getArgs();
                if (count($args) === 1) {
                    $isArrayCall = $fragment;
                    $argumentVariable = $args[0]->value;
                }
            }
            
            // Look for instanceof Traversable
            if ($fragment instanceof \PhpParser\Node\Expr\Instanceof_) {
                if ($fragment->class instanceof Name) {
                    $className = $fragment->class->toString();
                    if ($className === 'Traversable' || $className === '\Traversable') {
                        $instanceOfTraversable = $fragment;
                    }
                }
            }
        }

        // Build error only if all required elements are present and variables match
        if ($isArrayCall !== null && $instanceOfTraversable !== null && $argumentVariable !== null && 
            $this->areNodesEquivalent($argumentVariable, $instanceOfTraversable->expr)) {
            
            $variableText = $this->prettyPrinter->prettyPrint([$argumentVariable]);
            
            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    "'is_array(%s) || %s instanceof Traversable' can be replaced by 'is_iterable(%s)'.",
                    $variableText,
                    $variableText,
                    $variableText
                )
            )
            ->identifier('function.isIterableCanBeUsed')
            ->line($isArrayCall->getStartLine())
            ->tip('Use is_iterable() instead for better readability and performance')
            ->build();
        }

        return $errors;
    }

    /**
     * @return Node[]
     */
    private function extractOrFragments(BooleanOr $expression): array
    {
        $fragments = [];
        $this->collectOrFragments($expression, $fragments);
        return $fragments;
    }

    /**
     * @param Node[] $fragments
     */
    private function collectOrFragments(Node $node, array &$fragments): void
    {
        if ($node instanceof BooleanOr) {
            $this->collectOrFragments($node->left, $fragments);
            $this->collectOrFragments($node->right, $fragments);
        } else {
            $fragments[] = $node;
        }
    }

    private function areNodesEquivalent(Node $node1, Node $node2): bool
    {
        return $this->prettyPrinter->prettyPrint([$node1]) === $this->prettyPrinter->prettyPrint([$node2]);
    }
}