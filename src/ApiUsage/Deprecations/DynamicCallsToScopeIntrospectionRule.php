<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Deprecations;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects dynamic calls to scope introspection functions that are forbidden in PHP 7.1+.
 *
 * This rule identifies dynamic calls to functions like compact(), extract(), func_get_args(), etc.
 * that cannot be called dynamically in PHP 7.1 and later due to a backward compatibility break.
 * It also detects such calls when used as callbacks in functions like array_map().
 *
 * @implements \PHPStan\Rules\Rule<FuncCall>
 */
final class DynamicCallsToScopeIntrospectionRule implements Rule
{
    /** @var array<string, int> */
    private const TARGET_CALLS = [
        'compact' => -1,
        'extract' => -1,
        'func_get_args' => 0,
        'func_get_arg' => 1,
        'func_num_args' => 0,
        'get_defined_vars' => 0,
        'mb_parse_str' => 1,
        'parse_str' => 1,
    ];

    /** @var array<string, int> */
    private const CALLBACKS_POSITIONS = [
        'call_user_func' => 0,
        'call_user_func_array' => 0,
        'array_filter' => 1,
        'array_map' => 0,
        'array_reduce' => 1,
        'array_walk' => 1,
        'array_walk_recursive' => 1,
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof FuncCall) {
            return [];
        }

        $functionName = null;
        $targetNode = null;

        // Case 1: Direct dynamic call, e.g., $func()
        if ($node->name instanceof Node\Expr) {
            $targetNode = $node->name;
        } elseif ($node->name instanceof Node\Name) {
            $calledFunctionName = (string) $node->name;
            // Case 2: Callback-based dynamic call, e.g., array_map('compact', $data)
            if (isset(self::CALLBACKS_POSITIONS[$calledFunctionName])) {
                $callbackPosition = self::CALLBACKS_POSITIONS[$calledFunctionName];
                if (isset($node->args[$callbackPosition])) {
                    $targetNode = $node->args[$callbackPosition]->value;
                }
            }
        }

        if ($targetNode !== null) {
            // Try to resolve the target node to a string literal
            if ($targetNode instanceof String_) {
                $functionName = $targetNode->value;
            } elseif ($targetNode instanceof Variable) {
                // PHPStan's type inference might help here, but for a simple rule,
                // we might need to rely on literal strings.
                // For now, we only check direct string literals.
                // More advanced analysis would involve resolving variable types.
            }
        }

        if ($functionName !== null) {
            // Remove leading backslash if present (for fully qualified names)
            $functionName = ltrim($functionName, '\\');

            if (isset(self::TARGET_CALLS[$functionName])) {
                return [
                    RuleErrorBuilder::message(
                        sprintf('Emits a runtime warning (cannot call %s() dynamically).', $functionName)
                    )
                    ->identifier('php71.dynamicCallsToScopeIntrospection')
                    ->line($node->getStartLine())
                    ->build(),
                ];
            }
        }

        return [];
    }
}
