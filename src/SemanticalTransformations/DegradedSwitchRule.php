<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalTransformations;

use PhpParser\Node;
use PhpParser\Node\Stmt\Switch_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects degraded switch statements that can be simplified to if statements.
 *
 * This rule identifies switch constructs that behave like simpler conditional statements:
 * - Switch with only one case and no default: behaves as if statement
 * - Switch with only one case and a default: behaves as if-else statement
 * - Switch with no cases but only a default: can be simplified to just the default case body
 *
 * These patterns indicate that the switch statement is unnecessarily complex and
 * can be refactored to simpler conditional constructs for better readability.
 *
 * @implements Rule<Switch_>
 */
class DegradedSwitchRule implements Rule
{
    private const string MESSAGE_IF = 'Switch construct behaves as if, consider refactoring.';
    private const string MESSAGE_IF_ELSE = 'Switch construct behaves as if-else, consider refactoring.';
    private const string MESSAGE_ONLY_DEFAULT = 'Switch construct has default case only, consider leaving only the default case\'s body.';

    public function getNodeType(): string
    {
        return Switch_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Switch_) {
            return [];
        }

        $cases = $node->cases;
        $hasDefault = $this->hasDefaultCase($cases);

        // Count non-default cases
        $nonDefaultCaseCount = 0;
        foreach ($cases as $case) {
            if ($case->cond !== null) {
                $nonDefaultCaseCount++;
            }
        }

        // Check for degraded switch patterns
        if ($nonDefaultCaseCount === 0 && $hasDefault) {
            // Only default case
            return [
                RuleErrorBuilder::message(self::MESSAGE_ONLY_DEFAULT)
                    ->identifier('switch.degradedOnlyDefault')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        if ($nonDefaultCaseCount === 1) {
            if (!$hasDefault) {
                // One case, no default - behaves as if
                return [
                    RuleErrorBuilder::message(self::MESSAGE_IF)
                        ->identifier('switch.degradedIf')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }

            // One case with default - behaves as if-else
            return [
                RuleErrorBuilder::message(self::MESSAGE_IF_ELSE)
                    ->identifier('switch.degradedIfElse')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * Check if the switch statement has a default case
     *
     * @param array<Node\Stmt\Case_> $cases
     */
    private function hasDefaultCase(array $cases): bool
    {
        foreach ($cases as $case) {
            if ($case->cond === null) {
                return true;
            }
        }

        return false;
    }
}