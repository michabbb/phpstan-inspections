<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\DateTime;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects deprecated DateTime format constants that are not ISO-8601 compatible.
 *
 * This rule warns about usage of DateTime::ISO8601, DateTimeInterface::ISO8601, and DATE_ISO8601
 * constants, which are not truly compatible with ISO-8601 standard despite their names.
 * It suggests using DateTime::ATOM or DATE_ATOM instead for proper ISO-8601 compliance.
 *
 * @implements Rule<Node>
 */
class DateTimeConstantsUsageRule implements Rule
{
    private const string MESSAGE_CLASS_CONSTANT = 'The format is not compatible with ISO-8601. Use DateTime::ATOM for compatibility with ISO-8601 instead.';
    private const string MESSAGE_CONSTANT = 'The format is not compatible with ISO-8601. Use DATE_ATOM for compatibility with ISO-8601 instead.';
    
    private const array TARGET_CLASS_CONSTANTS = [
        'DateTime' => 'ISO8601',
        'DateTimeInterface' => 'ISO8601',
    ];
    
    private const string TARGET_GLOBAL_CONSTANT = 'DATE_ISO8601';

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check class constants (DateTime::ISO8601, DateTimeInterface::ISO8601)
        if ($node instanceof ClassConstFetch) {
            $errors = [...$errors, ...$this->checkClassConstant($node)];
        }

        // Check global constant (DATE_ISO8601)
        if ($node instanceof ConstFetch) {
            $errors = [...$errors, ...$this->checkGlobalConstant($node)];
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkClassConstant(ClassConstFetch $node): array
    {
        if (!$node->class instanceof Name || !$node->name instanceof Node\Identifier) {
            return [];
        }

        $className = $node->class->toString();
        $constantName = $node->name->toString();

        if ($constantName !== 'ISO8601') {
            return [];
        }

        // Check if it's one of our target classes
        if (!isset(self::TARGET_CLASS_CONSTANTS[$className])) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE_CLASS_CONSTANT)
                ->identifier('dateTime.iso8601Deprecated')
                ->tip('Use DateTime::ATOM instead of DateTime::ISO8601')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkGlobalConstant(ConstFetch $node): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $constantName = $node->name->toString();

        if ($constantName !== self::TARGET_GLOBAL_CONSTANT) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE_CONSTANT)
                ->identifier('dateTime.iso8601Deprecated')
                ->tip('Use DATE_ATOM instead of DATE_ISO8601')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}