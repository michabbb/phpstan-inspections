<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Unset_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects useless unset() operations on function/method parameters.
 * 
 * When unset() is applied to a parameter, it only destroys the local copy/reference
 * within the function scope, which is often unnecessary and can be removed.
 *
 * @implements Rule<Unset_>
 */
class UselessUnsetRule implements Rule
{
    public function getNodeType(): string
    {
        return Unset_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        
        // Must be inside a function or method
        $functionReflection = $scope->getFunction();
        if ($functionReflection !== null) {
            // Get the current function/method parameter names
            $parameterNames = $this->getCurrentFunctionParameterNames($scope);
            if ($parameterNames !== []) {
                // Check each variable being unset
                foreach ($node->vars as $var) {
                    // Check if the variable is a simple variable that matches a parameter
                    if ($var instanceof Variable && is_string($var->name) && in_array($var->name, $parameterNames, true)) {
                        $errors[] = RuleErrorBuilder::message('Only local copy/reference will be unset. This unset can probably be removed.')
                            ->identifier('unset.useless')
                            ->line($node->getStartLine())
                            ->build();
                            
                        // Only report once per unset statement, even if multiple parameters are unset
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function getCurrentFunctionParameterNames(Scope $scope): array
    {
        $function = $scope->getFunction();
        if ($function === null) {
            return [];
        }

        $parametersInfo = $function->getParameters();
        
        $parameterNames = [];
        foreach ($parametersInfo as $paramInfo) {
            $parameterNames[] = $paramInfo->getName();
        }
        
        return $parameterNames;
    }
}