<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Meldet array_push($arr, $value) bei Einzel-Element –
 * bevorzugt $arr[] = $value für Lesbarkeit/Performance.
 *
 * @implements Rule<FuncCall>
 */
final class ArrayPushMissUseRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /** @param FuncCall $node */
    public function processNode(Node $node, Scope $scope): array
    {
        // Nur direkte Funktionsaufrufe namens array_push prüfen
        if (!($node->name instanceof Node\Name) || $node->name->toString() !== 'array_push') {
            return [];
        }

        $argCount = count($node->getArgs());

        // Mehr als 2 Argumente (= mehrere Werte) sind OK; Miss-Use bei genau 2 (array + 1 value)
        if ($argCount === 2) {
            return [
                RuleErrorBuilder::message('Use $array[] = $value instead of array_push($array, $value) for a single element.')
                    ->identifier('arrayPush.singleElement')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
