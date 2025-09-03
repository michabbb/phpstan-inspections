<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<AssignRef>
 */
final class ReferencingObjectsInspectorRuleAssignRef implements Rule
{
    public const string IDENTIFIER = 'assignment.byRefNew';

    /**
     * @return class-string<AssignRef>
     */
    public function getNodeType(): string
    {
        return AssignRef::class;
    }

    /**
     * @param AssignRef $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->expr instanceof New_) {
            return [
                RuleErrorBuilder::message('Objects are always passed by reference; please correct "= & new ".')
                    ->identifier(self::IDENTIFIER)
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }
        return [];
    }
}

