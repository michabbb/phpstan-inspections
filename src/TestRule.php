<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Test rule for development and debugging purposes.
 *
 * This rule is used for testing the PHPStan rule framework and serves as a template
 * for creating new rules. It reports all function calls found in the code.
 *
 * @implements Rule<FuncCall>
 */
class TestRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        $functionName = $node->name instanceof Node\Name ? $node->name->toString() : 'unknown';

        return [
            RuleErrorBuilder::message("Found function call: {$functionName}")
                ->identifier('test.rule')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}