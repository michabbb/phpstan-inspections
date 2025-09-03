<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests using ob_get_clean() instead of ob_get_contents() + ob_end_clean().
 *
 * This rule detects when ob_get_contents() is called immediately followed by
 * ob_end_clean(). In such cases, ob_get_clean() can be used as a more concise
 * and efficient alternative that combines both operations in a single function call.
 *
 * @implements \PHPStan\Rules\Rule<FuncCall>
 */
final class ObGetCleanCanBeUsedRule implements Rule
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

        // Check if this is a function call to ob_end_clean
        if (!$node->name instanceof Node\Name) {
            return [];
        }
        
        $functionName = $node->name->toString();
        if ($functionName !== 'ob_end_clean') {
            return [];
        }

        // Check if ob_end_clean is a global function call
        if (!$this->isGlobalFunctionCall($node, $scope)) {
            return [];
        }

        // Get the parent statement of ob_end_clean()
        $parentStatement = $node->getAttribute('parent');
        if (!$parentStatement instanceof Expression) {
            return [];
        }

        // Get the previous statement
        $previousStatement = null;
        $current = $parentStatement;
        while ($current = $current->getAttribute('previous')) {
            if ($current instanceof Expression) {
                $previousStatement = $current;
                break;
            }
        }

        if (!$previousStatement instanceof Expression) {
            return [];
        }

        // Search for ob_get_contents() within the previous statement
        $nodeFinder = new \PhpParser\NodeFinder();
        $obGetContentsCall = $nodeFinder->findFirst($previousStatement, static function (Node $subNode) use ($scope): bool {
            if (!$subNode instanceof FuncCall) {
                return false;
            }
            if (!$subNode->name instanceof Node\Name) {
                return false;
            }
            $subFunctionName = $subNode->name->toString();
            if ($subFunctionName !== 'ob_get_contents') {
                return false;
            }
            return $this->isGlobalFunctionCall($subNode, $scope);
        });

        if ($obGetContentsCall instanceof FuncCall) {
            return [
                RuleErrorBuilder::message('\'ob_get_clean()\' can be used instead.')
                    ->identifier('outputBuffer.obGetCleanCanBeUsed')
                    ->line($obGetContentsCall->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function isGlobalFunctionCall(FuncCall $funcCall, Scope $scope): bool
    {
        // Check if the function name is a simple name (not a method call or fully qualified name)
        if (!$funcCall->name instanceof Node\Name) {
            return false;
        }

        // For built-in functions like ob_end_clean and ob_get_contents,
        // we can assume they are global functions if they're simple names.
        // This is a simplified check - in a full implementation we might
        // want to check the actual function definition.
        $name = $funcCall->name->toString();
        return in_array($name, ['ob_end_clean', 'ob_get_contents'], true);
    }
}
