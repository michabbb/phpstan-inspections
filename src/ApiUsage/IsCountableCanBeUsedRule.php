<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<BooleanOr>
 */
final class IsCountableCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return BooleanOr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof BooleanOr) {
            return [];
        }

        $left = $node->left;
        $right = $node->right;

        $isArrayCall = null;
        $instanceofCountable = null;

        if ($this->isIsArrayCall($left) && $this->isInstanceofCountable($right)) {
            $isArrayCall = $left;
            $instanceofCountable = $right;
        } elseif ($this->isIsArrayCall($right) && $this->isInstanceofCountable($left)) {
            $isArrayCall = $right;
            $instanceofCountable = $left;
        } else {
            return [];
        }

        if (!$this->areNodesEquivalent($isArrayCall->getArgs()[0]->value, $instanceofCountable->expr)) {
            return [];
        }

        $argumentText = $scope->getType($isArrayCall->getArgs()[0]->value)->describe(\PHPStan\Type\VerbosityLevel::value());
        $message = sprintf(
            '\'is_array(%s) || %s instanceof Countable\' can be replaced by \'is_countable(%s)\'',
            $argumentText,
            $argumentText,
            $argumentText
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('apiUsage.isCountableCanBeUsed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isIsArrayCall(Node $node): bool
    {
        return $node instanceof FuncCall &&
               $node->name instanceof Name &&
               (string) $node->name === 'is_array' &&
               count($node->getArgs()) === 1;
    }

    private function isInstanceofCountable(Node $node): bool
    {
        return $node instanceof Instanceof_ &&
               $node->class instanceof Name &&
               (string) $node->class === 'Countable';
    }

    private function areNodesEquivalent(Node $node1, Node $node2): bool
    {
        if ($node1 instanceof Node\Expr\Variable && $node2 instanceof Node\Expr\Variable) {
            if (is_string($node1->name) && is_string($node2->name)) {
                return $node1->name === $node2->name;
            }
        }
        return false;
    }
}