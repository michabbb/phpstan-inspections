<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Debug rule to test if custom rules are working
 */
final class DebugRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Expression::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return [
            RuleErrorBuilder::message('Debug rule is working!')
                ->identifier('debug.test')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}