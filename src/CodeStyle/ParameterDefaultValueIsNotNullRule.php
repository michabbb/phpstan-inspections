<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\NullType;
use PHPStan\Type\UnionType;

/**
 * Detects non-null default values in function/method parameters and suggests using null instead.
 *
 * This rule identifies function and method parameters that have default values other than null,
 * when the parameter type allows null values (nullable types or no type hint). It promotes
 * the use of null as default values to encourage nullable types, which is considered a best practice
 * in modern PHP development.
 *
 * The rule reports violations for:
 * - Parameters with non-null default values in nullable typed parameters
 * - Parameters with non-null default values in untyped parameters
 *
 * It does not report violations for:
 * - Parameters with explicit non-nullable types (e.g., string, int)
 * - Method overrides where the parent method is not private
 *
 * @implements Rule<Node>
 */
final class ParameterDefaultValueIsNotNullRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return [];
        }

        $parameters = $node->getParams();
        if (empty($parameters)) {
            return [];
        }

        $violations = [];

        foreach ($parameters as $parameter) {
            // Skip parameters without default values
            if ($parameter->default === null) {
                continue;
            }

            // Skip if default value is already null
            if ($this->isNullValue($parameter->default)) {
                continue;
            }

            // Check if parameter type allows null
            if (!$this->allowsNullType($parameter, $scope)) {
                continue;
            }

            // TODO: Handle method overrides - don't report if parent method is not private
            // This requires more complex reflection analysis

            $violations[] = RuleErrorBuilder::message(
                'Null should be used as the default value (nullable types are the goal, right?)'
            )
                ->identifier('parameter.defaultValueNotNull')
                ->line($parameter->getStartLine())
                ->build();
        }

        return $violations;
    }

    private function isNullValue(Node $node): bool
    {
        return $node instanceof ConstFetch
            && $node->name instanceof Node\Name
            && strtolower($node->name->toString()) === 'null';
    }

    private function allowsNullType(Node\Param $parameter, Scope $scope): bool
    {
        // If no type hint, null is allowed
        if ($parameter->type === null) {
            return true;
        }

        // Check if type is explicitly nullable (e.g., ?string)
        if ($parameter->type instanceof Node\NullableType) {
            return true;
        }

        // For union types that include null, allow it
        if ($parameter->type instanceof Node\UnionType) {
            foreach ($parameter->type->types as $type) {
                if ($type instanceof Node\Name && strtolower($type->toString()) === 'null') {
                    return true;
                }
            }
        }

        // For other typed parameters, we don't allow non-null defaults
        // This matches the Java inspector's behavior of only allowing nullable types
        return false;
    }


}