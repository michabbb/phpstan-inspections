<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects gettype() usage that can be replaced with more specific is_*() functions.
 * Based on GetTypeMissUseInspector.java from PhpStorm EA Extended.
 *
 * @implements Rule<BinaryOp>
 */
final class GetTypeMissUseRule implements Rule
{
    /** @var array<string, string> */
    private const TYPE_MAPPINGS = [
        'boolean'  => 'is_bool',
        'integer'  => 'is_int',
        'double'   => 'is_float',
        'string'   => 'is_string',
        'array'    => 'is_array',
        'object'   => 'is_object',
        'resource' => 'is_resource',
        'NULL'     => 'is_null',
    ];

    /** @var list<string> */
    private const VALID_TYPES = [
        'boolean', 'integer', 'double', 'string', 'array', 'object', 'resource', 'NULL',
        'unknown type', 'resource (closed)',
    ];

    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    /** @param BinaryOp $node */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only check equality comparisons (==, ===, !=, !==)
        if (!$this->isEqualityComparison($node)) {
            return [];
        }

        $gettypeCall = $this->findGettypeCall($node);
        if ($gettypeCall === null) {
            return [];
        }

        $stringLiteral = $this->findStringLiteral($node, $gettypeCall);
        if ($stringLiteral === null) {
            return [];
        }

        return $this->analyzeComparison($node, $gettypeCall, $stringLiteral);
    }

    private function isEqualityComparison(BinaryOp $node): bool
    {
        return $node instanceof BinaryOp\Equal
            || $node instanceof BinaryOp\Identical
            || $node instanceof BinaryOp\NotEqual
            || $node instanceof BinaryOp\NotIdentical;
    }

    private function findGettypeCall(BinaryOp $node): ?FuncCall
    {
        if ($node->left instanceof FuncCall && $this->isGettypeCall($node->left)) {
            return $node->left;
        }

        if ($node->right instanceof FuncCall && $this->isGettypeCall($node->right)) {
            return $node->right;
        }

        return null;
    }

    private function isGettypeCall(FuncCall $funcCall): bool
    {
        return $funcCall->name instanceof Node\Name
            && $funcCall->name->toString() === 'gettype'
            && count($funcCall->getArgs()) === 1;
    }

    private function findStringLiteral(BinaryOp $node, FuncCall $gettypeCall): ?String_
    {
        $otherOperand = $node->left === $gettypeCall ? $node->right : $node->left;

        return $otherOperand instanceof String_ ? $otherOperand : null;
    }

    /** @return list<\PHPStan\Rules\RuleError> */
    private function analyzeComparison(BinaryOp $node, FuncCall $gettypeCall, String_ $stringLiteral): array
    {
        $typeString = $stringLiteral->value;

        // Check for invalid type strings
        if (!in_array($typeString, self::VALID_TYPES, true)) {
            return [
                RuleErrorBuilder::message(sprintf("'%s' is not a value returned by 'gettype(...)'.", $typeString))
                    ->identifier('gettype.invalidType')
                    ->line($stringLiteral->getStartLine())
                    ->build(),
            ];
        }

        // Skip types that don't have is_* equivalents
        if (!isset(self::TYPE_MAPPINGS[$typeString])) {
            return [];
        }

        $isFunction = self::TYPE_MAPPINGS[$typeString];
        $argument   = $gettypeCall->getArgs()[0]->value;
        $isNegated  = $node instanceof BinaryOp\NotEqual || $node instanceof BinaryOp\NotIdentical;

        $replacement = sprintf('%s%s(%s)', $isNegated ? '!' : '', $isFunction, $this->nodeToString($argument));

        return [
            RuleErrorBuilder::message(sprintf("'%s' would fit more here (clearer expresses the intention and SCA friendly).", $replacement))
                ->identifier('gettype.canBeReplaced')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function nodeToString(Node $node): string
    {
        // Simple approximation - in a real implementation you might want more sophisticated handling
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return '$' . $node->name;
        }

        // For other complex expressions, fall back to a generic placeholder
        return '$variable';
    }
}
