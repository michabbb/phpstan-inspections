<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\FileSystem;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Meldet verschachtelte dirname(dirname($path)) Aufrufe –
 * Empfehlung: dirname($path, 2).
 *
 * @implements Rule<FuncCall>
 */
final class CascadingDirnameCallsRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @param FuncCall $node */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!($node->name instanceof Node\Name) || $node->name->toString() !== 'dirname') {
            return [];
        }

        // Nur 1-Argument-Variante prüfen (cascading pattern)
        $args = $node->getArgs();
        if (count($args) !== 1) {
            return [];
        }

        $inner = $args[0]->value;
        if ($inner instanceof FuncCall && $inner->name instanceof Node\Name && $inner->name->toString() === 'dirname') {
            // innere dirname ebenfalls nur mit 1 Argument?
            if (count($inner->getArgs()) === 1) {
                return [
                    RuleErrorBuilder::message('Use dirname($path, 2) instead of nested dirname(dirname($path)).')
                        ->identifier('dirname.cascading')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }
}
