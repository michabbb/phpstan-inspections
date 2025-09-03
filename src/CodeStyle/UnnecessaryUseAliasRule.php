<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unnecessary use aliases where the alias matches the class name.
 *
 * This rule identifies use statements with aliases that are redundant because
 * the alias name is identical to the last part of the fully qualified class name.
 * Such aliases add unnecessary verbosity to the code without providing any benefit.
 *
 * For example:
 * - use Some\Namespace\ClassName as ClassName; // Unnecessary alias
 * - use Some\Namespace\ClassName; // Preferred form
 *
 * The rule only applies to class imports, not function or constant imports.
 *
 * @implements Rule<Use_>
 */
final class UnnecessaryUseAliasRule implements Rule
{
    public function getNodeType(): string
    {
        return Use_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Use_) {
            return [];
        }

        // Only process class imports, not function or constant imports
        if ($node->type !== Use_::TYPE_NORMAL) {
            return [];
        }

        $errors = [];

        /** @var UseUse $use */
        foreach ($node->uses as $use) {
            $alias = $use->alias;
            if ($alias === null) {
                continue;
            }

            $aliasName = $alias->toString();
            if ($aliasName === '') {
                continue;
            }

            // Get the fully qualified name
            $fqn = $use->name->toString();

            // Check if the FQN ends with the alias (case-sensitive)
            if (str_ends_with($fqn, '\\' . $aliasName)) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf("' as %s' is redundant here.", $aliasName)
                )
                ->identifier('use.unnecessaryAlias')
                ->line($use->getStartLine())
                ->build();
            }
        }

        return $errors;
    }
}