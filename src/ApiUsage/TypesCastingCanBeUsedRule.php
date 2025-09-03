<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Int_ as CastInt;
use PhpParser\Node\Expr\Cast\Float_ as CastFloat;
use PhpParser\Node\Expr\Cast\String_ as CastString;
use PhpParser\Node\Expr\Cast\Bool_ as CastBool;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Scalar\String_ as StringScalar;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Ternary;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;

/**
 * Suggests using PHP type casting operators instead of legacy functions.
 *
 * This rule detects usage of PHP 4-style type casting functions (intval, floatval, strval, boolval)
 * and suggests using modern PHP 5+ type casting operators like (int), (float), (string), (bool).
 * Type casting operators are faster and more readable than function calls.
 *
 * @implements Rule<Node>
 */
final class TypesCastingCanBeUsedRule implements Rule
{
    private const string MESSAGE_PATTERN = "'%s' can be used instead (reduces cognitive load, up to 6x times faster in PHP 5.x).";
    private const string MESSAGE_INLINING = "'%s' would express the intention here better (less types coercion magic).";

    /** @var array<string, string> */
    private array $functionsMapping = [
        'intval' => 'int',
        'floatval' => 'float',
        'strval' => 'string',
        'boolval' => 'bool',
    ];

    /** @var array<string, string> */
    private array $typesMapping = [
        'boolean' => 'bool',
        'bool' => 'bool',
        'integer' => 'int',
        'int' => 'int',
        'float' => 'float',
        'double' => 'float',
        'string' => 'string',
        'array' => 'array',
    ];

    public function getNodeType(): string
    {
        return Node::class; // We will filter inside the rule
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        if ($node instanceof FuncCall) {
            $errors = array_merge($errors, $this->processFuncCall($node, $scope));
        } elseif ($node instanceof Encapsed) {
            $errors = array_merge($errors, $this->processEncapsedString($node, $scope));
        }

        return $errors;
    }

    private function processFuncCall(FuncCall $node, Scope $scope): array
    {
        $errors = [];
        $functionName = $node->name instanceof Node\Name ? $node->name->toString() : null;

        if ($functionName === null) {
            return $errors;
        }

        if (isset($this->functionsMapping[$functionName])) {
            $errors = array_merge($errors, $this->processSimpleTypeCastingFunctions($node, $scope, $functionName));
        } elseif ($functionName === 'settype') {
            $errors = array_merge($errors, $this->processSetTypeFunction($node, $scope));
        }

        return $errors;
    }

    private function processSimpleTypeCastingFunctions(FuncCall $node, Scope $scope, string $functionName): array
    {
        $errors = [];
        $args = $node->getArgs();

        $isTarget = count($args) === 1;
        if (! $isTarget && count($args) === 2 && $functionName === 'intval') {
            $secondArg = $args[1]->value;
            if ($secondArg instanceof LNumber && $secondArg->value === 10) {
                $isTarget = true;
            }
        }

        if ($isTarget) {
            $argument = $args[0]->value;
            $wrapArgument = $argument instanceof BinaryOp || $argument instanceof Ternary;
            $replacement = sprintf(
                '(%s) %s',
                $this->functionsMapping[$functionName],
                sprintf($wrapArgument ? '(%s)' : '%s', $this->getNodeText($argument))
            );

            $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_PATTERN, $replacement))
                ->identifier('typeCasting.canBeUsed')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function processSetTypeFunction(FuncCall $node, Scope $scope): array
    {
        $errors = [];
        $args = $node->getArgs();

        if (count($args) === 2) {
            $firstArg = $args[0]->value;
            $secondArg = $args[1]->value;

            if ($secondArg instanceof StringScalar) {
                $type = $secondArg->value;
                if (isset($this->typesMapping[$type])) {
                    // Check if the settype call is a statement
                    // In PHPStan, we can check if the parent is an expression statement
                    if ($node->getAttribute('parent') instanceof Node\Stmt\Expression) {
                        $replacement = sprintf(
                            '%s = (%s) %s',
                            $this->getNodeText($firstArg),
                            $this->typesMapping[$type],
                            $this->getNodeText($firstArg)
                        );

                        $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_PATTERN, $replacement))
                            ->identifier('typeCasting.canBeUsed')
                            ->line($node->getStartLine())
                            ->build();
                    }
                }
            }
        }

        return $errors;
    }

    private function processEncapsedString(Encapsed $node, Scope $scope): array
    {
        $errors = [];
        $parts = $node->parts;

        // Check for simple inlined string casting like "$foo" or "${foo}"
        if (count($parts) === 1 && $parts[0] instanceof Node\Expr) {
            $expression = $parts[0];
            $replacement = sprintf('(string) %s', $this->getNodeText($expression));

            $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_INLINING, $replacement))
                ->identifier('typeCasting.inlinedString')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function getNodeText(Node $node): string
    {
        // This is a simplified way to get the node's text.
        // For more complex scenarios, a NodePrinter or similar might be needed.
        return (string) $node->getAttribute('originalNode') ?? (string) $node;
    }
}
