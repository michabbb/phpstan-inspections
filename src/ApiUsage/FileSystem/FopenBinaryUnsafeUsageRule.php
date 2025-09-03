<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\FileSystem;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects binary-unsafe 'fopen(...)' usage.
 *
 * This rule identifies when fopen() is called with modes that are not binary-safe,
 * according to PHP documentation recommendations. It checks for:
 * - Missing 'b' modifier when neither 'b' nor 't' is present
 * - Incorrect placement of 'b' modifier (should be at the end)
 * - Presence of 't' modifier (should be replaced with 'b')
 *
 * @implements Rule<FuncCall>
 */
class FopenBinaryUnsafeUsageRule implements Rule
{
    private bool $enforceBinaryModifierUsage;

    public function __construct(bool $enforceBinaryModifierUsage = true)
    {
        $this->enforceBinaryModifierUsage = $enforceBinaryModifierUsage;
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

        // Check if this is an fopen call
        if (!$node->name instanceof Node\Name || $node->name->toString() !== 'fopen') {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 2) {
            return [];
        }

        // Get the mode argument (second parameter)
        $modeArg = $args[1];
        if (!$modeArg->value instanceof String_) {
            return [];
        }

        $modeText = $modeArg->value->value;
        if ($modeText === '') {
            return [];
        }

        $errors = [];

        if (str_contains($modeText, 'b')) {
            // Check if 'b' is correctly placed (at the end)
            $isCorrectlyPlaced = str_ends_with($modeText, 'b') || str_ends_with($modeText, 'b+');
            if (!$isCorrectlyPlaced) {
                $errors[] = RuleErrorBuilder::message(
                    "The 'b' modifier needs to be the last one (e.g 'wb', 'wb+')."
                )->identifier('fopen.binaryModifier')->line($node->getStartLine())->build();
            }
        } elseif (str_contains($modeText, 't')) {
            // 't' modifier should be replaced with 'b'
            if ($this->enforceBinaryModifierUsage) {
                $errors[] = RuleErrorBuilder::message(
                    "The mode is not binary-safe (replace 't' with 'b', as documentation recommends)."
                )->identifier('fopen.binaryModifier')->line($node->getStartLine())->build();
            }
        } else {
            // Neither 'b' nor 't' is present - suggest adding 'b'
            if ($this->enforceBinaryModifierUsage) {
                $errors[] = RuleErrorBuilder::message(
                    "The mode is not binary-safe ('b' is missing, as documentation recommends)."
                )->identifier('fopen.binaryModifier')->line($node->getStartLine())->build();
            }
        }

        return $errors;
    }
}