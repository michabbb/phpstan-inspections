<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Suggests using PHP constants instead of function calls for better performance.
 *
 * This rule detects function calls that can be replaced with equivalent PHP constants:
 * - phpversion() → PHP_VERSION
 * - php_sapi_name() → PHP_SAPI
 * - get_class() → __CLASS__ (in appropriate contexts)
 * - pi() → M_PI
 *
 * It also suggests using PHP_VERSION_ID for version comparisons instead of version_compare().
 *
 * @implements Rule<FuncCall>
 */
class ConstantCanBeUsedRule implements Rule
{
    private const array FUNCTION_TO_CONSTANT_MAPPING = [
        'phpversion'    => 'PHP_VERSION',
        'php_sapi_name' => 'PHP_SAPI',
        'get_class'     => '__CLASS__',
        'pi'            => 'M_PI',
    ];

    private const array VERSION_OPERATORS = [
        '<'  => '<',
        'lt' => '<',
        '<=' => '<=',
        'le' => '<=',
        '>'  => '>',
        'gt' => '>',
        '>=' => '>=',
        'ge' => '>=',
        '==' => '===',
        '='  => '===',
        'eq' => '===',
        '!=' => '!==',
        '<>' => '!==',
        'ne' => '!==',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();
        $errors       = [];

        // Check function to constant mappings
        if (isset(self::FUNCTION_TO_CONSTANT_MAPPING[$functionName])) {
            if (count($node->getArgs()) === 0) {
                $constant = self::FUNCTION_TO_CONSTANT_MAPPING[$functionName];
                $errors[] = RuleErrorBuilder::message(
                    $constant . ' constant should be used instead.'
                )
                    ->identifier('constant.functionReplacement')
                    ->tip('Replace ' . $functionName . '() with ' . $constant)
                    ->build();
            }
        }

        // Check version_compare with PHP_VERSION
        if ($functionName === 'version_compare' && count($node->getArgs()) === 3) {
            $errors = [...$errors, ...$this->checkVersionCompare($node)];
        }

        return $errors;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkVersionCompare(FuncCall $node): array
    {
        $args = $node->getArgs();
        
        // Check if first argument is PHP_VERSION constant
        if (!$args[0]->value instanceof ConstFetch) {
            return [];
        }

        $constantName = $args[0]->value->name->toString();
        if ($constantName !== 'PHP_VERSION') {
            return [];
        }

        // Check if second argument is a version string
        if (!$args[1]->value instanceof String_) {
            return [];
        }

        $versionString = $args[1]->value->value;

        // Check if third argument is an operator string
        if (!$args[2]->value instanceof String_) {
            return [];
        }

        $operator = $args[2]->value->value;

        if (!isset(self::VERSION_OPERATORS[$operator])) {
            return [];
        }

        // Parse version string (format: X.Y.Z where Y and Z are optional)
        if (preg_match('/^(\d)(\.(\d)(\.(\d+))?)?$/', $versionString, $matches) !== 1) {
            return [];
        }

        $major = $matches[1];
        $minor = $matches[3] ?? '0';
        $patch = $matches[5] ?? '0';

        // Format version ID: XXYYZZ (pad with zeros)
        $versionId   = sprintf('%d%02d%02d', (int) $major, (int) $minor, (int) $patch);
        $phpOperator = self::VERSION_OPERATORS[$operator];

        $replacement = 'PHP_VERSION_ID ' . $phpOperator . ' ' . $versionId;

        return [
            RuleErrorBuilder::message(
                "Consider using '" . $replacement . "' instead."
            )
                ->identifier('constant.phpVersionId')
                ->tip('PHP_VERSION_ID provides faster integer comparison')
                ->build(),
        ];
    }
}
