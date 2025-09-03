<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast\Bool_;
use PhpParser\Node\Expr\Paren;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects multiple consecutive NOT operators that reduce code readability.
 *
 * This rule identifies expressions with multiple consecutive NOT operators (!!, !!!, etc.)
 * which can be confusing and hard to understand. It suggests clearer alternatives:
 * - !!$var → (bool) $var
 * - !!!$var → ! $var
 *
 * @implements Rule<BooleanNot>
 */
final class NestedNotOperatorsRule implements Rule
{
    private static array $processedExpressions = [];

    public function getNodeType(): string
    {
        return BooleanNot::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof BooleanNot) {
            return [];
        }

        $errors = [];

        // Count nested NOT operators by looking inward
        $nestingLevel = $this->countNestedNotOperators($node);
        
        if ($nestingLevel > 1) {
            // Create a unique key for this expression to avoid duplicates
            $innermost = $this->getInnermostExpression($node);
            // Use only the innermost expression position - the nesting level will be the same for all parts
            $key = $innermost->getStartLine() . ':' . $innermost->getEndLine();
            
            // Skip if we've already processed this exact expression
            if (isset(self::$processedExpressions[$key])) {
                return $errors;
            }
            
            self::$processedExpressions[$key] = true;
            
            // Create pretty printer for reconstruction
            $printer = new \PhpParser\PrettyPrinter\Standard();
            $subject = $printer->prettyPrintExpr($innermost);

            // If the innermost expression is a binary operation, wrap it in parentheses
            if ($innermost instanceof BinaryOp) {
                $subject = '(' . $subject . ')';
            }

            $replacement = '';
            if ($nestingLevel % 2 === 0) { // Even nesting: !!$foo -> (bool) $foo
                $replacement = '(bool) ' . $subject;
            } else { // Odd nesting: !!!$foo -> ! $foo
                $replacement = '! ' . $subject;
            }

            $errors[] = RuleErrorBuilder::message(
                sprintf('Can be replaced with %s.', $replacement)
            )
                ->identifier('codeStyle.nestedNotOperators')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    /**
     * Count how many nested NOT operators there are, starting from the given node
     */
    private function countNestedNotOperators(BooleanNot $node): int
    {
        $count = 1; // Current node is already a NOT
        $current = $node->expr;

        // Skip parentheses and look for inner NOT operators
        while ($current instanceof Paren) {
            $current = $current->expr;
        }

        // If we find another NOT operator, recursively count
        if ($current instanceof BooleanNot) {
            $count += $this->countNestedNotOperators($current);
        }

        return $count;
    }

    /**
     * Get the innermost non-NOT expression
     */
    private function getInnermostExpression(BooleanNot $node): Node
    {
        $current = $node->expr;

        // Skip parentheses and traverse inner NOT operators
        while (true) {
            // Skip parentheses
            while ($current instanceof Paren) {
                $current = $current->expr;
            }

            // If we find another NOT operator, go deeper
            if ($current instanceof BooleanNot) {
                $current = $current->expr;
            } else {
                break;
            }
        }

        return $current;
    }
}
