<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Deprecations;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NullType;
use PHPStan\Type\TypeCombinator;

/**
 * Detects usage of get_class() with potentially null arguments.
 *
 * Since PHP 7.2, the get_class() function does not accept null as an argument.
 * This rule identifies calls to get_class() where the argument could be null,
 * either because the type includes null or because it's a nullable parameter.
 *
 * @implements Rule<FuncCall>
 */
class GetClassUsageRule implements Rule
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

        // Check if this is a get_class function call
        if (!$node->name instanceof Node\Name || $node->name->toString() !== 'get_class') {
            return [];
        }

        // get_class should have exactly one argument
        if (count($node->getArgs()) !== 1) {
            return [];
        }

        $arg = $node->getArgs()[0];
        $argType = $scope->getType($arg->value);

        // Check if the type could be null
        if (!TypeCombinator::containsNull($argType)) {
            return [];
        }

        // TODO: Add logic to check if nullability is already verified
        // This would require more complex analysis of preceding statements

        return [
            RuleErrorBuilder::message(
                "'get_class(...)' does not accept null as argument in PHP 7.2+ versions."
            )
                ->identifier('function.getClassNullArgument')
                ->build(),
        ];
    }
}