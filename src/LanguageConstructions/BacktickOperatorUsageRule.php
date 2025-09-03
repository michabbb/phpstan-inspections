<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\ShellExec;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of the backtick operator and suggests using 'shell_exec(...)' instead.
 *
 * This rule identifies backtick operator usage (`command`) and recommends replacing it with
 * shell_exec("command") for better security analysis compatibility. The backtick operator is
 * rarely used and can be problematic for static analysis tools.
 *
 * @implements Rule<ShellExec>
 */
final class BacktickOperatorUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return ShellExec::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ShellExec) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                "Prefer using 'shell_exec(...)' instead (security analysis friendly)."
            )
            ->identifier('backtick.securityAnalysis')
            ->line($node->getStartLine())
            ->build(),
        ];
    }
}