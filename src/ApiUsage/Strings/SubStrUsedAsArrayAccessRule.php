<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Strings;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

/**
 * Detects 'substr(...)' used as index-based access and suggests array access instead.
 *
 * This rule identifies substr() calls that can be replaced with array access:
 * - substr($string, $offset, 1) → $string[$offset] (for positive offsets)
 * - substr($string, -1, 1) → $string[strlen($string) - 1] (for negative offsets)
 *
 * Using array access is preferred because it makes error-handling logic more explicit
 * and can help catch invalid index accesses at runtime.
 *
 * @implements Rule<FuncCall>
 */
final class SubStrUsedAsArrayAccessRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        // Check if this is a substr function call
        if (!$this->isSubstrFunction($node)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) !== 3) {
            return [];
        }

        // Check if the length argument is exactly 1
        $lengthArg = $args[2]->value;
        if (!$this->isLengthOne($lengthArg)) {
            return [];
        }

        // Check if the source is a valid type (variable, array access, or property)
        $sourceArg = $args[0]->value;
        if (!$this->isValidSource($sourceArg)) {
            return [];
        }

        // Check if the source resolves to a string type
        $sourceType = $scope->getType($sourceArg);
        if (!$this->isStringType($sourceType)) {
            return [];
        }

        // Generate the replacement suggestion
        $replacement = $this->generateReplacement($sourceArg, $args[1]->value);

        return [
            RuleErrorBuilder::message(
                sprintf("'%s' might be used instead (invalid index accesses might show up).", $replacement)
            )
                ->identifier('string.substrAsArrayAccess')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isSubstrFunction(FuncCall $node): bool
    {
        if (!$node->name instanceof Name) {
            return false;
        }

        return $node->name->toString() === 'substr';
    }

    private function isLengthOne(Node $lengthArg): bool
    {
        return $lengthArg instanceof LNumber && $lengthArg->value === 1;
    }

    private function isValidSource(Node $sourceArg): bool
    {
        return $sourceArg instanceof Variable
            || $sourceArg instanceof ArrayDimFetch
            || $sourceArg instanceof PropertyFetch;
    }

    private function isStringType(Type $type): bool
    {
        return $type instanceof StringType;
    }

    private function generateReplacement(Node $source, Node $offset): string
    {
        $sourceText = $this->nodeToString($source);
        $offsetText = $this->nodeToString($offset);

        // Check if offset is negative (starts with '-')
        if ($offset instanceof LNumber && $offset->value < 0) {
            return sprintf('%s[strlen(%s) %s]', $sourceText, $sourceText, $offsetText);
        }

        return sprintf('%s[%s]', $sourceText, $offsetText);
    }

    private function nodeToString(Node $node): string
    {
        // For simple cases, we can reconstruct the text representation
        // This is a simplified approach - in a production rule you might want
        // to use a more sophisticated approach to handle complex expressions
        if ($node instanceof Variable) {
            return '$' . $node->name;
        }

        if ($node instanceof LNumber) {
            return (string) $node->value;
        }

        // For more complex expressions, fall back to a generic representation
        // In practice, you might want to use the original source code
        return '...';
    }
}