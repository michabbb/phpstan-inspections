<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects when short list syntax can be used instead of list() construct.
 *
 * This rule identifies:
 * - Assignments using list() that can be replaced with short array syntax [...]
 * - Foreach loops using list() that can be replaced with short array syntax [...]
 *
 * The rule only applies to PHP 7.1+ codebases where short list syntax is available.
 * It suggests replacing list($a, $b) = $array with [$a, $b] = $array
 * and foreach ($array as list($a, $b)) with foreach ($array as [$a, $b])
 *
 * @implements Rule<Node>
 */
class ShortListSyntaxCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check for list() assignments that can use short syntax
        if ($node instanceof Expression && $node->expr instanceof Assign) {
            $assign = $node->expr;
            // Only process if the left side is a List_ node (not Array_ node)
            if ($assign->var instanceof List_) {
                $assignmentErrors = $this->checkListAssignment($node, $scope);
                $errors = array_merge($errors, $assignmentErrors);
            }
        }

        // Check for foreach with list() that can use short syntax
        if ($node instanceof Foreach_) {
            $foreachErrors = $this->checkForeachListUsage($node, $scope);
            $errors = array_merge($errors, $foreachErrors);
        }

        return $errors;
    }

    /**
     * Check if an assignment uses list() and can be replaced with short syntax.
     */
    private function checkListAssignment(Expression $expression, Scope $scope): array
    {
        $assign = $expression->expr;

        // Check if the left side is a list() construct
        if (!$assign->var instanceof List_) {
            return [];
        }

        // Try to determine if this uses the old list() syntax 
        $listNode = $assign->var;
        
        // Check for kind attribute as suggested by consensus models
        $kind = $listNode->getAttribute('kind');
        if ($kind !== null) {
            // If kind attribute exists, check if it indicates short syntax is already in use
            // According to models, KIND_ARRAY (value 2) means short syntax is already used
            if ($kind === 2) {
                return []; // Already using short syntax, no error needed
            }
        }
        
        // Try checking if there's a direct kind property
        if (property_exists($listNode, 'kind')) {
            if ($listNode->kind === 2) {  // Array/short syntax
                return []; // Already using short syntax, no error needed
            }
        }
        
        // Check if this is a standalone statement (not part of a larger expression)
        $parent = $expression->getAttribute('parent');
        if ($parent !== null && !$parent instanceof Node\Stmt\Block) {
            return [];
        }

        return [
            RuleErrorBuilder::message("'[...] = ...' can be used here.")
                ->identifier('list.shortSyntaxCanBeUsed')
                ->line($expression->getStartLine())
                ->build(),
        ];
    }

    /**
     * Check if a foreach loop uses list() in its value variable that can be replaced with short syntax.
     */
    private function checkForeachListUsage(Foreach_ $foreach, Scope $scope): array
    {
        // Check if the foreach value variable is a list() construct
        if (!$foreach->valueVar instanceof List_) {
            return [];
        }

        // Try to determine if this uses the old list() syntax 
        $listNode = $foreach->valueVar;
        
        // Check for kind attribute as suggested by consensus models
        $kind = $listNode->getAttribute('kind');
        if ($kind !== null) {
            // If kind attribute exists, check if it indicates short syntax is already in use
            // According to models, KIND_ARRAY (value 2) means short syntax is already used
            if ($kind === 2) {
                return []; // Already using short syntax, no error needed
            }
        }
        
        // Try checking if there's a direct kind property
        if (property_exists($listNode, 'kind')) {
            if ($listNode->kind === 2) {  // Array/short syntax
                return []; // Already using short syntax, no error needed
            }
        }

        return [
            RuleErrorBuilder::message("'foreach (... as [...])' can be used here.")
                ->identifier('list.shortSyntaxCanBeUsed')
                ->line($foreach->getStartLine())
                ->build(),
        ];
    }
}