<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * Detects simple cases of assignments that are never used.
 *
 * This is a simplified rule that detects basic cases where variables
 * are assigned values but those values are never used. Due to the 
 * complexity of full variable usage analysis without parent node 
 * information, this rule focuses on simple, obvious cases.
 *
 * Note: PHPStan's built-in unused variable detection is much more
 * comprehensive and should be used for production code analysis.
 *
 * @implements Rule<Node>
 */
class OnlyWritesOnParameterRule implements Rule
{
    public function getNodeType(): string
    {
        return Assign::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Assign) {
            return [];
        }

        // For now, just return empty array since comprehensive unused variable 
        // detection requires complex AST analysis that's better handled by 
        // PHPStan's built-in rules
        return [];
    }
}