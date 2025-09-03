<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Stmt\Foreach_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unsupported empty list assignments in foreach loops.
 *
 * This rule identifies empty list() or [] assignments in foreach loops that cause
 * PHP Fatal errors. Empty list assignments are not supported in PHP 7.0+.
 * See PHP migration guide for variable handling changes.
 *
 * @implements Rule<Foreach_>
 */
final class UnsupportedEmptyListAssignmentsRule implements Rule
{
    public function getNodeType(): string
    {
        return Foreach_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Foreach_) {
            return [];
        }

        $errors = [];

        // Check if keyVar is an empty list assignment
        if ($node->keyVar instanceof Array_ && empty($node->keyVar->items)) {
            $errors[] = RuleErrorBuilder::message('Provokes a PHP Fatal error (Cannot use empty list).')
                ->identifier('languageConstructions.unsupportedEmptyListAssignment')
                ->line($node->keyVar->getStartLine())
                ->build();
        } elseif ($node->keyVar instanceof List_ && empty($node->keyVar->items)) {
            $errors[] = RuleErrorBuilder::message('Provokes a PHP Fatal error (Cannot use empty list).')
                ->identifier('languageConstructions.unsupportedEmptyListAssignment')
                ->line($node->keyVar->getStartLine())
                ->build();
        }

        // Check if valueVar is an empty list assignment (only if keyVar is not set, or if it's a normal variable)
        // The Java inspector specifically looks for list() or [] after 'as'.
        // If keyVar is not an empty list, then valueVar is the primary target.
        if (!($node->keyVar instanceof Array_ && empty($node->keyVar->items)) &&
            !($node->keyVar instanceof List_ && empty($node->keyVar->items))) {
            if ($node->valueVar instanceof Array_ && empty($node->valueVar->items)) {
                $errors[] = RuleErrorBuilder::message('Provokes a PHP Fatal error (Cannot use empty list).')
                    ->identifier('languageConstructions.unsupportedEmptyListAssignment')
                    ->line($node->valueVar->getStartLine())
                    ->build();
            } elseif ($node->valueVar instanceof List_ && empty($node->valueVar->items)) {
                $errors[] = RuleErrorBuilder::message('Provokes a PHP Fatal error (Cannot use empty list).')
                    ->identifier('languageConstructions.unsupportedEmptyListAssignment')
                    ->line($node->valueVar->getStartLine())
                    ->build();
            }
        }


        return $errors;
    }
}
