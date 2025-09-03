<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of simplexml_load_file() which may be affected by PHP bug #62577.
 *
 * This rule warns about simplexml_load_file() function calls that can be affected
 * by a PHP bug where the function may not work correctly in certain scenarios.
 * Suggests using simplexml_load_string(file_get_contents($file), ...) as an alternative.
 *
 * @implements Rule<FuncCall>
 */
class SimpleXmlLoadFileUsageRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name) {
            return [];
        }

        $functionName = $node->name->toString();
        
        if ($functionName !== 'simplexml_load_file') {
            return [];
        }

        return [
            RuleErrorBuilder::message('This can be affected by a PHP bug #62577 (https://bugs.php.net/bug.php?id=62577)')
                ->identifier('simplexml.loadFile.bug')
                ->tip('Consider using simplexml_load_string(file_get_contents($file), ...) instead')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
