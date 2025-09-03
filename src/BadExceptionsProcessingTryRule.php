<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects try blocks with too many statements that should be refactored.
 *
 * This rule identifies try blocks containing more than 3 statements,
 * suggesting they should be extracted into separate methods or functions.
 *
 * @implements Rule<TryCatch>
 */
class BadExceptionsProcessingTryRule implements Rule
{
    public const string MESSAGE_TOO_MANY_STATEMENTS = 'It is possible that some of the statements contained in the try block can be extracted into their own methods or functions (we recommend that you do not include more than three statements per try block).';

    public function getNodeType(): string
    {
        return TryCatch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof TryCatch) {
            return [];
        }

        $statementCount = count($node->stmts);

        if ($statementCount > 3) {
            return [
                RuleErrorBuilder::message(self::MESSAGE_TOO_MANY_STATEMENTS)
                    ->identifier('exception.tryBlockTooLarge')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}