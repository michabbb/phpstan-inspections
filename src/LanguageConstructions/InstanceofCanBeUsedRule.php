<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects cases where 'instanceof' operator can be used instead of function calls.
 *
 * This rule identifies patterns that can be replaced with the more efficient and readable 'instanceof' operator:
 * - get_class($obj) == 'ClassName' → $obj instanceof ClassName
 * - get_parent_class($obj) == 'ClassName' → $obj instanceof ClassName
 * - is_a($obj, 'ClassName') → $obj instanceof ClassName
 * - is_subclass_of($obj, 'ClassName') → $obj instanceof ClassName
 * - in_array('ClassName', class_implements($obj)) → $obj instanceof ClassName
 * - in_array('ClassName', class_parents($obj)) → $obj instanceof ClassName
 *
 * @implements Rule<FuncCall>
 */
final class InstanceofCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return [];
        }

        $functionName = (string) $node->name;

        switch ($functionName) {
            case 'get_class':
            case 'get_parent_class':
                return $this->analyzeGetClassCall($node, $scope, $functionName === 'get_parent_class');

            case 'is_a':
            case 'is_subclass_of':
                return $this->analyzeIsACall($node, $scope);

            case 'in_array':
                return $this->analyzeInArrayCall($node, $scope);

            default:
                return [];
        }
    }

    /**
     * @return list<RuleError>
     */
    private function analyzeGetClassCall(FuncCall $node, Scope $scope, bool $allowChildClasses): array
    {
        $args = $node->getArgs();
        if (count($args) !== 1) {
            return [];
        }

        // Check if this is in a binary comparison context
        $parent = $node->getAttribute('parent');
        if (!$parent instanceof BinaryOp || !$this->isComparisonOperator($parent)) {
            return [];
        }

        // Find the position of our function call in the binary operation
        $subject = null;
        $isInverted = false;

        if ($parent->left === $node) {
            $subject = $parent->right;
            $isInverted = $this->isInvertedOperator($parent);
        } elseif ($parent->right === $node) {
            $subject = $parent->left;
            $isInverted = $this->isInvertedOperator($parent);
        }

        if ($subject === null) {
            return [];
        }

        $fqn = $this->extractClassFqn($subject);
        if ($fqn === null) {
            return [];
        }

        $subjectText = $this->printNode($args[0]->value);
        $replacement = $isInverted
            ? "! {$subjectText} instanceof {$fqn}"
            : "{$subjectText} instanceof {$fqn}";

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' can be used instead.", $replacement)
            )
                ->identifier('instanceof.canBeUsed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * @return list<RuleError>
     */
    private function analyzeIsACall(FuncCall $node, Scope $scope): array
    {
        $args = $node->getArgs();
        if (count($args) < 2 || count($args) > 3) {
            return [];
        }

        // Check third argument (allow_string) - if present and false, we can suggest instanceof
        if (count($args) === 3) {
            $allowStringType = $scope->getType($args[2]->value);
            if (!$allowStringType->isFalse()->yes()) {
                return [];
            }
        }

        $fqn = $this->extractClassFqn($args[1]->value);
        if ($fqn === null) {
            return [];
        }

        $subjectText = $this->printNode($args[0]->value);
        $replacement = "{$subjectText} instanceof {$fqn}";

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' can be used instead.", $replacement)
            )
                ->identifier('instanceof.canBeUsed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * @return list<RuleError>
     */
    private function analyzeInArrayCall(FuncCall $node, Scope $scope): array
    {
        $args = $node->getArgs();
        if (count($args) < 2) {
            return [];
        }

        $haystack = $args[1]->value;
        if (!$haystack instanceof FuncCall || !$haystack->name instanceof Name) {
            return [];
        }

        $innerFunctionName = (string) $haystack->name;
        if (!in_array($innerFunctionName, ['class_implements', 'class_parents'], true)) {
            return [];
        }

        $innerArgs = $haystack->getArgs();
        if (count($innerArgs) === 0) {
            return [];
        }

        $fqn = $this->extractClassFqn($args[0]->value);
        if ($fqn === null) {
            return [];
        }

        $subjectText = $this->printNode($innerArgs[0]->value);
        $replacement = "{$subjectText} instanceof {$fqn}";

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' can be used instead.", $replacement)
            )
                ->identifier('instanceof.canBeUsed')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isComparisonOperator(BinaryOp $node): bool
    {
        return $node instanceof Equal
            || $node instanceof Identical
            || $node instanceof NotEqual
            || $node instanceof NotIdentical;
    }

    private function isInvertedOperator(BinaryOp $node): bool
    {
        return $node instanceof NotEqual || $node instanceof NotIdentical;
    }

    private function extractClassFqn(Node $node): ?string
    {
        if (!$node instanceof String_) {
            return null;
        }

        $className = $node->value;
        if (strlen($className) < 3 || $className === '__PHP_Incomplete_Class') {
            return null;
        }

        // Convert backslash escapes
        return '\\' . str_replace('\\\\', '\\', $className);
    }

    private function printNode(Node $node): string
    {
        $printer = new \PhpParser\PrettyPrinter\Standard();
        return $printer->prettyPrintExpr($node);
    }
}