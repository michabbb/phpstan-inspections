<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions\NullCoalescing;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects isset, empty, and null coalescing operations on undefined variables.
 *
 * This rule identifies:
 * - isset() calls with variables that are not defined in the current scope
 * - empty() calls with variables that are not defined in the current scope
 * - Null coalescing (??) operations with undefined variables on the left side
 *
 * The rule considers function parameters, use variables, and special PHP variables
 * as defined. It also handles dynamic variable creation in loops.
 *
 * @implements Rule<Node>
 */
class IssetArgumentExistenceRule implements Rule
{
    private const string MESSAGE_PATTERN = "'$%s' seems to be not defined in the scope.";

    /**
     * Special variables that are always available in PHP
     */
    private const array SPECIAL_VARIABLES = [
        'this',
        'php_errormsg',
        'http_response_header',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Handle isset() calls
        if ($node instanceof Isset_) {
            $errors = array_merge($errors, $this->analyzeIssetArguments($node->vars, $scope));
        }

        // Handle empty() calls
        elseif ($node instanceof Empty_) {
            $errors = array_merge($errors, $this->analyzeIssetArguments([$node->expr], $scope));
        }

        // Handle null coalescing operations
        elseif ($node instanceof Coalesce && $node->left instanceof Variable) {
            $errors = array_merge($errors, $this->analyzeVariable($node->left, $scope));
        }

        return $errors;
    }

    /**
     * Analyze arguments of isset() or empty() calls
     *
     * @param Node[] $arguments
     * @return array
     */
    private function analyzeIssetArguments(array $arguments, Scope $scope): array
    {
        $errors = [];

        foreach ($arguments as $argument) {
            if ($argument instanceof Variable) {
                $errors = array_merge($errors, $this->analyzeVariable($argument, $scope));
            }
        }

        return $errors;
    }

    /**
     * Analyze a single variable to check if it's defined in scope
     *
     * @return array
     */
    private function analyzeVariable(Variable $variable, Scope $scope): array
    {
        $variableName = $variable->name;

        // Skip if not a string variable name
        if (!is_string($variableName)) {
            return [];
        }

        // Skip special variables
        if (in_array($variableName, self::SPECIAL_VARIABLES, true)) {
            return [];
        }

        // Check if variable is defined in the current scope using PHPStan's scope analysis
        if (!$scope->hasVariableType($variableName)->yes()) {
            return [
                RuleErrorBuilder::message(
                    sprintf(self::MESSAGE_PATTERN, $variableName)
                )
                    ->identifier('isset.argumentExistence')
                    ->line($variable->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}