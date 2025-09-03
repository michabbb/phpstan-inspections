<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Ifs;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects if-return-return constructs that can be simplified.
 *
 * This rule identifies:
 * - If statements with binary expression conditions
 * - If body containing exactly one return statement (true/false)
 * - Either an else branch with a return statement, or a return immediately after the if
 * - Return statements that are return true/false or return false/true
 *
 * These patterns can be simplified to direct return of the condition (possibly inverted).
 *
 * @implements Rule<If_>
 */
class IfReturnReturnSimplificationRule implements Rule
{
    public function getNodeType(): string
    {
        return If_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof If_) {
            return [];
        }

        // Check if condition is a binary expression
        if (!$node->cond instanceof BinaryOp) {
            return [];
        }

        // Check if if body has exactly one statement
        if (count($node->stmts) !== 1) {
            return [];
        }

        $ifStatement = $node->stmts[0];
        if (!$ifStatement instanceof Return_) {
            return [];
        }

        $firstReturn = $ifStatement;

        // Check if first return is return true or return false
        $firstReturnValue = $this->getReturnValue($firstReturn);
        if ($firstReturnValue === null) {
            return [];
        }

        $secondReturn = null;
        $elseBranch = $node->else;

        if ($elseBranch !== null) {
            // Check else branch
            if (count($elseBranch->stmts) !== 1) {
                return [];
            }

            $elseStatement = $elseBranch->stmts[0];
            if (!$elseStatement instanceof Return_) {
                return [];
            }

            $secondReturn = $elseStatement;
        } else {
            // Check for return immediately after if
            $nextSibling = $node->getAttribute('next');
            if (!$nextSibling instanceof Return_) {
                return [];
            }

            $secondReturn = $nextSibling;
        }

        // Check if second return is return true or return false
        $secondReturnValue = $this->getReturnValue($secondReturn);
        if ($secondReturnValue === null) {
            return [];
        }

        // Check if returns are true/false or false/true
        $isDirect = $firstReturnValue === true && $secondReturnValue === false;
        $isReverse = $firstReturnValue === false && $secondReturnValue === true;

        if (!$isDirect && !$isReverse) {
            return [];
        }

        // False positive check: avoid consecutive if-return-return patterns
        if ($elseBranch === null) {
            $prevSibling = $node->getAttribute('previous');
            if ($prevSibling instanceof If_ && $this->hasReturnBody($prevSibling)) {
                return [];
            }
        }

        // Generate replacement suggestion
        $conditionText = $this->getConditionText($node->cond);
        $replacement = $isReverse
            ? 'return !(' . $conditionText . ');'
            : 'return ' . $conditionText . ';';

        return [
            RuleErrorBuilder::message('The construct can be replaced with \'' . $replacement . '\'.')
                ->identifier('controlFlow.ifReturnReturnSimplification')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function getReturnValue(Return_ $return): ?bool
    {
        if ($return->expr === null) {
            return null;
        }

        if ($return->expr instanceof Node\Expr\ConstFetch) {
            $name = $return->expr->name;
            if ($name instanceof Node\Name) {
                $constName = strtolower($name->toString());
                if ($constName === 'true') {
                    return true;
                }
                if ($constName === 'false') {
                    return false;
                }
            }
        }

        return null;
    }

    private function hasReturnBody(If_ $if): bool
    {
        if (count($if->stmts) !== 1) {
            return false;
        }

        return $if->stmts[0] instanceof Return_;
    }

    private function getConditionText(Node $condition): string
    {
        // For simplicity, we'll reconstruct the condition text
        // In a more sophisticated implementation, we could use a printer
        if ($condition instanceof BinaryOp) {
            $left = $this->getConditionText($condition->left);
            $right = $this->getConditionText($condition->right);
            $operator = $this->getOperatorText($condition);
            return $left . ' ' . $operator . ' ' . $right;
        }

        if ($condition instanceof Node\Expr\Variable) {
            return '$' . $condition->name;
        }

        if ($condition instanceof Node\Expr\ConstFetch) {
            return $condition->name->toString();
        }

        if ($condition instanceof Node\Expr\PropertyFetch) {
            $var = $this->getConditionText($condition->var);
            $name = $condition->name instanceof Node\Identifier
                ? $condition->name->toString()
                : $this->getConditionText($condition->name);
            return $var . '->' . $name;
        }

        if ($condition instanceof Node\Expr\MethodCall) {
            $var = $this->getConditionText($condition->var);
            $name = $condition->name instanceof Node\Identifier
                ? $condition->name->toString()
                : $this->getConditionText($condition->name);
            return $var . '->' . $name . '()';
        }

        // Fallback for other expressions
        return '...';
    }

    private function getOperatorText(BinaryOp $op): string
    {
        return match (get_class($op)) {
            Node\Expr\BinaryOp\Equal::class => '==',
            Node\Expr\BinaryOp\Identical::class => '===',
            Node\Expr\BinaryOp\NotEqual::class => '!=',
            Node\Expr\BinaryOp\NotIdentical::class => '!==',
            Node\Expr\BinaryOp\Greater::class => '>',
            Node\Expr\BinaryOp\GreaterOrEqual::class => '>=',
            Node\Expr\BinaryOp\Less::class => '<',
            Node\Expr\BinaryOp\LessOrEqual::class => '<=',
            Node\Expr\BinaryOp\BooleanAnd::class => '&&',
            Node\Expr\BinaryOp\BooleanOr::class => '||',
            default => 'op',
        };
    }
}