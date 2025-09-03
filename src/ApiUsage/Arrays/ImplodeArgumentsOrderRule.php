<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects implode() calls with wrong arguments order.
 * Ensures the glue argument is the first one, as per official documentation.
 * Based on ImplodeArgumentsOrderInspector.java from PhpStorm EA Extended.
 *
 * @implements Rule<FuncCall>
 */
final class ImplodeArgumentsOrderRule implements Rule
{
    private const string MESSAGE = 'The glue argument should be the first one.';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @param FuncCall $node */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isImplodeCall($node)) {
            return [];
        }

        if (count($node->getArgs()) !== 2) {
            return [];
        }

        $args = $node->getArgs();

        // Check if the second argument is a string literal (indicating likely wrong order)
        // The Java inspector specifically checks if arguments[1] is StringLiteralExpression
        if (!($args[1]->value instanceof String_)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('implode.argumentsOrder')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isImplodeCall(FuncCall $funcCall): bool
    {
        return $funcCall->name instanceof Node\Name
            && $funcCall->name->toString() === 'implode';
    }
}
