<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests replacing is_null() function with null comparison operators.
 *
 * This rule detects usage of the is_null() function (introduced in PHP 4) and suggests
 * replacing it with more modern null comparison operators:
 * - is_null($var) → $var === null
 * - !is_null($var) → $var !== null
 * - is_null($var) == true → $var === null
 * - is_null($var) == false → $var !== null
 *
 * Using === and !== operators is more readable and consistent with modern PHP practices.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\FuncCall>
 */
final class IsNullFunctionUsageRule implements Rule
{
    private Standard $prettyPrinter;

    public function __construct()
    {
        $this->prettyPrinter = new Standard();
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        $functionName = $node->name;
        if (!$functionName instanceof Node\Name || $functionName->toLowerString() !== 'is_null') {
            return [];
        }

        if (count($node->getArgs()) !== 1) {
            return [];
        }

        $argument = $node->getArgs()[0]->value;
        $wrappedArgument = $this->wrapArgumentIfNeeded($argument);

        $parent = $node->getAttribute('parent');
        $checksIsNull = true;
        $suggestedReplacement = '';

        if ($parent instanceof BooleanNot) {
            // Case: !is_null($var)
            $checksIsNull = false;
            $suggestedReplacement = $wrappedArgument . ' !== null';
        } elseif ($parent instanceof BinaryOp) {
            // Case: is_null($var) == true, is_null($var) === false, etc.
            $otherOperand = null;
            if ($parent->left === $node) {
                $otherOperand = $parent->right;
            } elseif ($parent->right === $node) {
                $otherOperand = $parent->left;
            }

            if ($otherOperand instanceof ConstFetch && $otherOperand->name->toLowerString() === 'true') {
                if ($parent instanceof Equal || $parent instanceof Identical) {
                    $checksIsNull = true; // is_null($var) == true  => $var === null
                } elseif ($parent instanceof NotEqual || $parent instanceof NotIdentical) {
                    $checksIsNull = false; // is_null($var) != true => $var !== null
                }
            } elseif ($otherOperand instanceof ConstFetch && $otherOperand->name->toLowerString() === 'false') {
                if ($parent instanceof Equal || $parent instanceof Identical) {
                    $checksIsNull = false; // is_null($var) == false => $var !== null
                } elseif ($parent instanceof NotEqual || $parent instanceof NotIdentical) {
                    $checksIsNull = true; // is_null($var) != false => $var === null
                }
            }

            // If we are inside a binary operation, the replacement should be for the whole expression
            // The Java inspector suggests 'null === ...' or 'null !== ...'
            if ($checksIsNull) {
                $suggestedReplacement = 'null === ' . $wrappedArgument;
            } else {
                $suggestedReplacement = 'null !== ' . $wrappedArgument;
            }

        } else {
            // Case: is_null($var) directly
            $suggestedReplacement = 'null === ' . $wrappedArgument;
        }

        return [
            RuleErrorBuilder::message(
                sprintf("All 'is_null(...)' calls can be safely replaced with '%s' constructs.", $suggestedReplacement)
            )
            ->identifier('apiUsage.isNullFunctionUsage')
            ->line($node->getStartLine())
            ->build(),
        ];
    }

    private function wrapArgumentIfNeeded(Node\Expr $argument): string
    {
        // Replicate Java's wrap logic: AssignmentExpression, TernaryExpression, BinaryExpression
        if ($argument instanceof Assign || $argument instanceof Ternary || $argument instanceof BinaryOp) {
            return '(' . $this->prettyPrinter->prettyPrintExpr($argument) . ')';
        }
        return $this->prettyPrinter->prettyPrintExpr($argument);
    }
}
