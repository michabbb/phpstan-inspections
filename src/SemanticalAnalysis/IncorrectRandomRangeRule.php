<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\UnionType;

/**
 * Detects incorrect random generation range in PHP random functions.
 *
 * This rule identifies calls to mt_rand(), random_int(), or rand() where the
 * minimum value is greater than the maximum value, which would result in
 * incorrect random number generation or runtime errors.
 *
 * The rule checks for patterns like:
 * - mt_rand(10, 5) - minimum > maximum
 * - random_int(100, 50) - minimum > maximum
 * - rand(20, 10) - minimum > maximum
 *
 * @implements Rule<Node\Expr\FuncCall>
 */
class IncorrectRandomRangeRule implements Rule
{
    private const TARGET_FUNCTIONS = ['mt_rand', 'random_int', 'rand'];

    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name) {
            return [];
        }

        $functionName = $node->name->toString();
        if (!in_array($functionName, self::TARGET_FUNCTIONS, true)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) !== 2) {
            return [];
        }

        $minArg = $args[0]->value;
        $maxArg = $args[1]->value;

        $minValue = $this->getConstantValue($scope->getType($minArg));
        $maxValue = $this->getConstantValue($scope->getType($maxArg));

        if ($minValue === null || $maxValue === null) {
            return [];
        }

        if ($maxValue < $minValue) {
            return [
                RuleErrorBuilder::message('The range is not defined properly.')
                    ->identifier('random.incorrectRange')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function getConstantValue(\PHPStan\Type\Type $type): ?int
    {
        if ($type instanceof ConstantIntegerType) {
            return $type->getValue();
        }

        if ($type instanceof ConstantStringType) {
            $stringValue = $type->getValue();
            if (is_numeric($stringValue)) {
                $numericValue = (int) $stringValue;
                if ((string) $numericValue === $stringValue) {
                    return $numericValue;
                }
            }
        }

        if ($type instanceof UnionType) {
            foreach ($type->getTypes() as $unionType) {
                $value = $this->getConstantValue($unionType);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }
}