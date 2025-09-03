<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects methods/functions with too many return statements.
 *
 * This rule identifies methods and functions that have multiple return points,
 * which can make code harder to understand and maintain. It suggests consolidating
 * multiple return statements into a single exit point to improve code clarity.
 *
 * Configurable thresholds via phpstan.neon:
 * - complainThreshold (default: 3): Warning threshold
 * - screamThreshold (default: 5): Strong warning threshold
 *
 * @implements \PHPStan\Rules\Rule<ClassMethod>
 * @implements \PHPStan\Rules\Rule<Function_>
 */
final class MultipleReturnStatementsRule implements Rule
{
    private const MESSAGE_PATTERN = 'Method has %s return points, try to introduce just one to uncover complexity behind.';

    public function __construct(
        private int $complainThreshold = 3,
        private int $screamThreshold = 5
    ) {
    }

    public function getNodeType(): string
    {
        return Node\FunctionLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod && !$node instanceof Function_) {
            return [];
        }

        if ($node instanceof ClassMethod && $node->isAbstract()) {
            return [];
        }

        $nodeFinder = new NodeFinder();
        $returnStatements = $nodeFinder->findInstanceOf($node->stmts, Return_::class);
        $returnsCount = count($returnStatements);

        if ($returnsCount >= $this->screamThreshold) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE_PATTERN, $returnsCount))
                    ->identifier('method.multipleReturns')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        } elseif ($returnsCount >= $this->complainThreshold) {
            return [
                RuleErrorBuilder::message(sprintf(self::MESSAGE_PATTERN, $returnsCount))
                    ->identifier('method.multipleReturns')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}
