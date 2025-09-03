<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects inefficient string start-with checks using strpos/stripos with === 0 comparison.
 *
 * This rule identifies patterns where strpos() or stripos() is used to check if a string
 * starts with another string by comparing the result with 0. Such patterns can be optimized
 * by using strncmp() or strncasecmp() instead, which are fixed-time operations and more
 * efficient for this specific use case.
 *
 * Examples of detected patterns:
 * - strpos($haystack, $needle) === 0
 * - stripos($haystack, $needle) !== 0
 *
 * Recommended replacements:
 * - strncmp($haystack, $needle, strlen($needle)) === 0
 * - strncasecmp($haystack, $needle, strlen($needle)) !== 0
 *
 * @implements Rule<FuncCall>
 */
class FixedTimeStartWithRule implements Rule
{
    private const MAPPING = [
        'strpos' => 'strncmp',
        'stripos' => 'strncasecmp',
    ];

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Look for binary operations with comparison operators
        if (!($node instanceof Identical) && !($node instanceof NotIdentical) &&
            !($node instanceof Equal) && !($node instanceof NotEqual)) {
            return [];
        }

        $errors = [];
        
        // Check both sides of the comparison
        $functionCall = null;
        $comparisonValue = null;
        
        // Check left side is function call, right side is 0
        if ($node->left instanceof FuncCall && $node->right instanceof LNumber && $node->right->value === 0) {
            $functionCall = $node->left;
            $comparisonValue = $node->right;
        }
        // Check right side is function call, left side is 0
        elseif ($node->right instanceof FuncCall && $node->left instanceof LNumber && $node->left->value === 0) {
            $functionCall = $node->right;
            $comparisonValue = $node->left;
        } else {
            return [];
        }

        // Check if it's strpos or stripos
        if (!$functionCall->name instanceof Node\Name) {
            return [];
        }

        $functionName = $functionCall->name->toString();
        if (!isset(self::MAPPING[$functionName])) {
            return [];
        }

        // Check if we have exactly 2 arguments
        if (count($functionCall->args) !== 2) {
            return [];
        }

        $secondArg = $functionCall->args[1]->value ?? null;
        
        // For string literals, we can use the literal length
        // For variables, we need to use strlen() call
        if ($secondArg instanceof String_) {
            $needleLength = strlen($secondArg->value);
            $lengthExpr = (string) $needleLength;
        } else {
            // For variables or other expressions, use strlen()
            $lengthExpr = 'strlen(' . $this->getNodeText($secondArg) . ')';
        }

        // Build the suggested replacement
        $replacement = sprintf(
            '%s(%s, %s, %s) %s 0',
            self::MAPPING[$functionName],
            $this->getNodeText($functionCall->args[0]->value),
            $this->getNodeText($secondArg),
            $lengthExpr,
            $this->getOperatorText($node)
        );

        $errors[] = RuleErrorBuilder::message(
            sprintf(
                "'%s' would be a solution not depending on the string length.",
                $replacement
            )
        )
        ->identifier('string.fixedTimeStartWith')
        ->line($functionCall->getStartLine())
        ->build();

        return $errors;
    }

    private function getNodeText(Node $node): string
    {
        // For simple cases, we can reconstruct the text
        if ($node instanceof Node\Expr\Variable) {
            return '$' . $node->name;
        }

        if ($node instanceof String_) {
            return "'" . addslashes($node->value) . "'";
        }

        // Use a pretty printer for more complex expressions
        $printer = new \PhpParser\PrettyPrinter\Standard();
        return $printer->prettyPrintExpr($node);
    }

    private function getOperatorText(Node $node): string
    {
        if ($node instanceof Identical) {
            return '===';
        }
        if ($node instanceof NotIdentical) {
            return '!==';
        }
        if ($node instanceof Equal) {
            return '==';
        }
        if ($node instanceof NotEqual) {
            return '!=';
        }
        return '';
    }
}