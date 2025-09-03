<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<ClassMethod>
 */
final class ReferencingObjectsInspectorRuleMethod implements Rule
{
    public const string IDENTIFIER = 'parameter.byRefObject';

    /**
     * @return class-string<ClassMethod>
     */
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return ReferencingObjectsInspectorRuleShared::processFunctionLike($node, $scope);
    }
}

