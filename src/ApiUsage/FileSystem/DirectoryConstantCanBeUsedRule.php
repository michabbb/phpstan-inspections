<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\FileSystem;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\MagicConst;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of dirname(__FILE__) and suggests using __DIR__ instead.
 *
 * This rule identifies function calls to dirname() with __FILE__ as the argument,
 * which can be replaced with the more efficient __DIR__ constant.
 *
 * - dirname(__FILE__) → __DIR__
 *
 * This is a micro-optimization that improves performance and readability.
 *
 * @implements Rule<FuncCall>
 */
class DirectoryConstantCanBeUsedRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();
        if ($functionName !== 'dirname') {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) !== 1) {
            return [];
        }

        $arg = $args[0]->value;
        if (!$arg instanceof MagicConst\File) {
            return [];
        }

        return [
            RuleErrorBuilder::message("'__DIR__' should be used instead.")
                ->identifier('directory.dirnameFile')
                ->tip('Replace dirname(__FILE__) with __DIR__ for better performance')
                ->build(),
        ];

        return [
            RuleErrorBuilder::message("'__DIR__' should be used instead.")
                ->identifier('directory.dirnameFile')
                ->tip('Replace dirname(__FILE__) with __DIR__ for better performance')
                ->build(),
        ];
    }
}