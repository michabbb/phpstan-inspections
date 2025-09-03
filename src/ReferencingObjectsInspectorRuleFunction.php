<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<Function_>
 */
final class ReferencingObjectsInspectorRuleFunction implements Rule
{
    public const string IDENTIFIER = 'parameter.byRefObject';

    /**
     * @return class-string<Function_>
     */
    public function getNodeType(): string
    {
        return Function_::class;
    }

    /**
     * @param Function_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return ReferencingObjectsInspectorRuleShared::processFunctionLike($node, $scope);
    }
}

