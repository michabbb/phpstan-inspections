<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ForEach;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Implements AlterInForeachInspector from Php Inspections (EA Extended).
 *
 * This rule detects issues with foreach variables reference usage correctness:
 * - Missing unset after foreach with reference variables (&$value)
 * - Unnecessary unset for non-reference variables
 * - Suggests using reference in array assignments within foreach loops
 *
 * The rule analyzes foreach statements to ensure proper handling of reference variables
 * and prevent potential side-effects from leftover references.
 */
final class AlterInForeachInspectorRule implements Rule
{
    public const string IDENTIFIER_MISSING_UNSET = 'foreach.reference.missingUnset';
    public const string IDENTIFIER_UNNECESSARY_UNSET = 'foreach.reference.unnecessaryUnset';
    public const string IDENTIFIER_SUGGEST_REFERENCE = 'foreach.reference.suggestReference';

    /**
     * @return class-string<Node>
     */
    public function getNodeType(): string
    {
        return Foreach_::class;
    }

    /**
     * @param Foreach_ $node
     * @return array<int, \PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check for missing unset after foreach with reference
        $missingUnsetErrors = $this->checkMissingUnset($node, $scope);
        $errors = array_merge($errors, $missingUnsetErrors);

        // Check for unnecessary unset of non-reference variables
        $unnecessaryUnsetErrors = $this->checkUnnecessaryUnset($node, $scope);
        $errors = array_merge($errors, $unnecessaryUnsetErrors);

        // Check for array assignments that could benefit from reference
        $suggestReferenceErrors = $this->checkSuggestReference($node, $scope);
        $errors = array_merge($errors, $suggestReferenceErrors);

        return $errors;
    }

    /**
     * Check for missing unset after foreach with reference variables.
     */
    private function checkMissingUnset(Foreach_ $foreach, Scope $scope): array
    {
        $errors = [];

        // Check if foreach value is by reference
        if ($foreach->byRef !== true) {
            return $errors;
        }

        $valueVar = $foreach->valueVar;
        if (!$valueVar instanceof Variable || !is_string($valueVar->name)) {
            return $errors;
        }

        $varName = $valueVar->name;

        // Find the next statement after foreach
        $nextStatement = $this->findNextStatement($foreach);

        // If there's no next statement (end of file/block), we need unset
        if ($nextStatement === null) {
            $errors[] = RuleErrorBuilder::message(
                'This variable must be unset just after foreach to prevent possible side-effects.'
            )
                ->identifier(self::IDENTIFIER_MISSING_UNSET)
                ->line($valueVar->getStartLine())
                ->build();
            return $errors;
        }

        // Check if there's an unset for this variable
        $hasUnset = $this->hasUnsetForVariable($nextStatement, $varName);

        // Check if next statement is a return, throw, or end of control flow
        $isControlFlowEnd = $this->isControlFlowEnd($nextStatement);

        if (!$hasUnset && !$isControlFlowEnd) {
            $errors[] = RuleErrorBuilder::message(
                'This variable must be unset just after foreach to prevent possible side-effects.'
            )
                ->identifier(self::IDENTIFIER_MISSING_UNSET)
                ->line($valueVar->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Check for unnecessary unset of non-reference variables.
     */
    private function checkUnnecessaryUnset(Foreach_ $foreach, Scope $scope): array
    {
        $errors = [];

        // Only check non-reference foreach
        if ($foreach->byRef === true) {
            return $errors;
        }

        $valueVar = $foreach->valueVar;
        if (!$valueVar instanceof Variable || !is_string($valueVar->name)) {
            return $errors;
        }

        $varName = $valueVar->name;

        // Find the next statement after foreach
        $nextStatement = $this->findNextStatement($foreach);
        if (!$nextStatement instanceof Unset_) {
            return $errors;
        }

        // Check if unset contains this variable
        if ($this->hasUnsetForVariable($nextStatement, $varName)) {
            $errors[] = RuleErrorBuilder::message(
                'Unsetting $' . $varName . ' is not needed because it\'s not a reference.'
            )
                ->identifier(self::IDENTIFIER_UNNECESSARY_UNSET)
                ->line($nextStatement->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Check for array assignments that could benefit from reference.
     */
    private function checkSuggestReference(Foreach_ $foreach, Scope $scope): array
    {
        $errors = [];

        // Skip if already by reference
        if ($foreach->byRef === true) {
            return $errors;
        }

        $valueVar = $foreach->valueVar;
        $keyVar = $foreach->keyVar;

        if (!$valueVar instanceof Variable || !is_string($valueVar->name)) {
            return $errors;
        }

        if (!$keyVar instanceof Variable || !is_string($keyVar->name)) {
            return $errors;
        }

        $valueName = $valueVar->name;
        $keyName = $keyVar->name;

        // Find array assignments within the foreach body
        $finder = new NodeFinder();
        $assignments = $finder->find($foreach->stmts, static function (Node $node) use ($valueName, $keyName): bool {
            if (!$node instanceof Assign) {
                return false;
            }

            // Check if left side is array access
            if (!$node->var instanceof ArrayDimFetch) {
                return false;
            }

            $arrayDim = $node->var;

            // Check if array variable matches foreach source
            if (!$arrayDim->var instanceof Variable) {
                return false;
            }

            // Check if index matches foreach key
            if (!$arrayDim->dim instanceof Variable) {
                return false;
            }

            return $arrayDim->var->name === $valueName && $arrayDim->dim->name === $keyName;
        });

        foreach ($assignments as $assignment) {
            $errors[] = RuleErrorBuilder::message(
                'Can be refactored as \'$' . $valueName . ' = ...\' if $' . $valueName . ' is defined as a reference (ensure that array supplied). Suppress if causes memory mismatches.'
            )
                ->identifier(self::IDENTIFIER_SUGGEST_REFERENCE)
                ->line($assignment->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Find the next statement after the foreach.
     */
    private function findNextStatement(Foreach_ $foreach): ?Node
    {
        // Get the parent that contains the statements
        $parent = $foreach->getAttribute('parent');

        if ($parent instanceof Node\Stmt\Namespace_) {
            $statements = $parent->stmts;
        } elseif ($parent instanceof Node\Stmt\Block) {
            $statements = $parent->stmts;
        } elseif ($parent instanceof Node\Stmt\Function_ ||
                  $parent instanceof Node\Stmt\ClassMethod) {
            $statements = $parent->stmts;
        } else {
            // For top-level statements, try to find the root statements
            $statements = $this->findRootStatements($foreach);
        }

        if ($statements === null) {
            return null;
        }

        // Find the index of our foreach in the statements
        $foreachIndex = array_search($foreach, $statements, true);
        if ($foreachIndex === false) {
            return null;
        }

        // Return the next statement if it exists
        if ($foreachIndex + 1 < count($statements)) {
            return $statements[$foreachIndex + 1];
        }

        return null;
    }

    private function findRootStatements(Foreach_ $foreach): ?array
    {
        $current = $foreach;
        while ($current !== null) {
            $parent = $current->getAttribute('parent');
            if ($parent === null) {
                // We've reached the root
                return null;
            }

            if ($parent instanceof Node\Stmt\Namespace_) {
                return $parent->stmts;
            }

            // Check if parent has stmts property
            if (property_exists($parent, 'stmts') && is_array($parent->stmts)) {
                return $parent->stmts;
            }

            $current = $parent;
        }
        return null;
    }

    /**
     * Check if a statement contains an unset for the given variable.
     */
    private function hasUnsetForVariable(Node $statement, string $varName): bool
    {
        // Handle direct Unset_ statements
        if ($statement instanceof Unset_) {
            foreach ($statement->vars as $var) {
                if ($var instanceof Variable && $var->name === $varName) {
                    return true;
                }
            }
            return false;
        }

        // Handle Expression statements that contain Unset_
        if ($statement instanceof Node\Stmt\Expression) {
            $expr = $statement->expr;
            if ($expr instanceof Unset_) {
                foreach ($expr->vars as $var) {
                    if ($var instanceof Variable && $var->name === $varName) {
                        return true;
                    }
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Check if the statement represents end of control flow.
     */
    private function isControlFlowEnd(Node $statement): bool
    {
        // Handle direct control flow statements
        if ($statement instanceof Node\Stmt\Return_
            || $statement instanceof Node\Stmt\Throw_
            || $statement instanceof Node\Stmt\Break_
            || $statement instanceof Node\Stmt\Continue_
            || $statement instanceof Node\Stmt\Exit_) {
            return true;
        }

        // Handle Expression statements that contain control flow
        if ($statement instanceof Node\Stmt\Expression) {
            return $this->isControlFlowEnd($statement->expr);
        }

        return false;
    }
}