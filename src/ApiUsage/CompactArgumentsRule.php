<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects potential issues with compact() function arguments.
 *
 * This rule checks if variables passed to compact() are actually defined in the current scope,
 * helping to prevent runtime errors when undefined variables are encountered.
 *
 * @implements Rule<FuncCall>
 */
class CompactArgumentsRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Early exits combined into single guard clause
        if (!$node->name instanceof Name ||
            strtolower($node->name->toString()) !== 'compact' ||
            count($node->getArgs()) === 0 ||
            $scope->getFunction() === null) {
            return [];
        }

        $errors = [];
        $args   = $node->getArgs();

        foreach ($args as $arg) {
            if (!$arg->value instanceof String_) {
                continue;
            }

            $variableName = $arg->value->value;
            if ($variableName === '') {
                continue;
            }

            if (!$scope->hasVariableType($variableName)->yes()) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf("'$%s' might not be defined in the scope.", $variableName)
                )
                    ->identifier('compact.undefinedVariable')
                    ->line($arg->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}
