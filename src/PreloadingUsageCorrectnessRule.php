<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures correct usage of opcache preloading in preload.php files.
 *
 * This rule detects include/require statements in preload.php files and suggests
 * using opcache_compile_file() instead. This is important for proper opcache
 * preloading behavior, as include statements in preload scripts can cause issues.
 * See PHP bug #78918 for technical details.
 *
 * @implements Rule<Expression>
 */
final class PreloadingUsageCorrectnessRule implements Rule
{
    public function getNodeType(): string
    {
        return Expression::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Expression) {
            return [];
        }

        if (!str_ends_with($scope->getFile(), 'preload.php')) {
            return [];
        }

        if ($node->expr instanceof Include_) {
            return [
                RuleErrorBuilder::message(
                    'Perhaps it should be used \'opcache_compile_file()\' here. See https://bugs.php.net/bug.php?id=78918 for details.'
                )
                ->identifier('preloading.usageCorrectness')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        return [];
    }
}
