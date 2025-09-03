<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\FileSystem;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects phar-incompatible 'realpath(...)' usage.
 *
 * This rule identifies problematic usage of realpath() that works differently
 * in stream contexts (e.g., for phar://...). It detects:
 * - realpath() applied to paths containing '..' (relative path references)
 *
 * In such cases, dirname() should be used instead as it handles streams correctly.
 *
 * @implements Rule<FuncCall>
 */
final class RealpathInStreamContextRule implements Rule
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

        // Check if this is a realpath call
        if (!$node->name instanceof Node\Name || $node->name->toString() !== 'realpath') {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) === 0) {
            return [];
        }

        // Check if realpath is applied to paths containing '..'
        if ($this->containsRelativePathReferences($args[0]->value)) {
            return [
                RuleErrorBuilder::message(
                    "'realpath(...)' works differently in a stream context (e.g., for phar://...). Consider using 'dirname(...)' instead."
                )
                    ->identifier('realpath.streamContext')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function containsRelativePathReferences(Node $argument): bool
    {
        // Find all string literals in the argument expression
        $nodeFinder = new NodeFinder();
        $stringLiterals = $nodeFinder->findInstanceOf($argument, String_::class);

        foreach ($stringLiterals as $literal) {
            if (str_contains($literal->value, '..')) {
                return true;
            }
        }

        return false;
    }
}