<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Arrays;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests using array_unique() instead of array_count_values() patterns.
 *
 * This rule detects when array_count_values() is used to get unique values or count unique elements,
 * and suggests using array_unique() instead, which is more readable and was optimized in PHP 7.2+.
 * It identifies patterns like array_keys(array_count_values($array)) and count(array_count_values($array)).
 *
 * @implements Rule<FuncCall>
 */
final class ArrayUniqueCanBeUsedRule implements Rule
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

        $errors = [];

        // Check for array_keys(array_count_values($array))
        if ($node->name instanceof Name && $node->name->toString() === 'array_keys') {
            if (count($node->getArgs()) === 1) {
                $firstArg = $node->getArgs()[0]->value;
                if ($firstArg instanceof FuncCall && $firstArg->name instanceof Name && $firstArg->name->toString() === 'array_count_values') {
                    if (count($firstArg->getArgs()) === 1) {
                        $arrayVar = $firstArg->getArgs()[0]->value;
                        $varName = $this->extractVariableName($arrayVar);
                        if ($varName !== null) {
                            $replacement = 'array_values(array_unique(' . $varName . '))';
                            $errors[] = RuleErrorBuilder::message(
                                "'" . $replacement . "' would be more readable here (array_unique(...) was optimized in PHP 7.2-beta3+)."
                            )
                                ->identifier('arrayUnique.canBeUsed')
                                ->line($node->getStartLine())
                                ->build();
                        }
                    }
                }
            }
        }

        // Check for count(array_count_values($array))
        if ($node->name instanceof Name && $node->name->toString() === 'count') {
            if (count($node->getArgs()) === 1) {
                $firstArg = $node->getArgs()[0]->value;
                if ($firstArg instanceof FuncCall && $firstArg->name instanceof Name && $firstArg->name->toString() === 'array_count_values') {
                    if (count($firstArg->getArgs()) === 1) {
                        $arrayVar = $firstArg->getArgs()[0]->value;
                        $varName = $this->extractVariableName($arrayVar);
                        if ($varName !== null) {
                            $replacement = 'count(array_unique(' . $varName . '))';
                            $errors[] = RuleErrorBuilder::message(
                                "'" . $replacement . "' would be more readable here (array_unique(...) was optimized in PHP 7.2-beta3+)."
                            )
                                ->identifier('arrayUnique.canBeUsed')
                                ->line($node->getStartLine())
                                ->build();
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private function extractVariableName(Node $node): ?string
    {
        if ($node instanceof Variable && is_string($node->name)) {
            return '$' . $node->name;
        }
        
        if ($node instanceof Name) {
            return $node->toString();
        }
        
        return null;
    }
}
