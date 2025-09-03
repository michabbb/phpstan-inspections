<?php

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Ensures JSON functions use JSON_THROW_ON_ERROR flag for better error handling.
 *
 * This rule detects calls to json_encode() and json_decode() that don't use the
 * JSON_THROW_ON_ERROR flag. Without this flag, these functions return false/null
 * on errors instead of throwing exceptions, making error handling more difficult.
 *
 * Suggests adding JSON_THROW_ON_ERROR to the flags parameter for better error handling.
 *
 * @implements Rule<Node\Expr\FuncCall>
 */
class JsonThrowOnErrorRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name) {
            return [];
        }

        $functionName = strtolower($node->name->toString());
        
        if (!in_array($functionName, ['json_decode', 'json_encode'], true)) {
            return [];
        }

        $args = $node->getArgs();
        $flagsArg = null;

        if ($functionName === 'json_decode') {
            // Look for named 'flags' argument
            foreach ($args as $arg) {
                if ($arg->name instanceof Node\Identifier && $arg->name->toString() === 'flags') {
                    $flagsArg = $arg;
                    break;
                }
            }

            // If not found, check for 4th positional argument
            if ($flagsArg === null && isset($args[3]) && $args[3]->name === null) {
                $flagsArg = $args[3];
            }
        } elseif ($functionName === 'json_encode') {
            // Look for named 'flags' argument  
            foreach ($args as $arg) {
                if ($arg->name instanceof Node\Identifier && $arg->name->toString() === 'flags') {
                    $flagsArg = $arg;
                    break;
                }
            }

            // If not found, check for 2nd positional argument
            if ($flagsArg === null && isset($args[1]) && $args[1]->name === null) {
                $flagsArg = $args[1];
            }
        }

        if ($flagsArg === null) {
            return [
                RuleErrorBuilder::message(
                    $functionName . ' is called without the JSON_THROW_ON_ERROR flag.'
                )->tip('Please consider taking advantage of JSON_THROW_ON_ERROR flag for this call options.')
                 ->build()
            ];
        }

        if (!$this->containsFlag($flagsArg->value, 'JSON_THROW_ON_ERROR')) {
             return [
                RuleErrorBuilder::message(
                    $functionName . ' is called without the JSON_THROW_ON_ERROR flag.'
                )->tip('Please consider taking advantage of JSON_THROW_ON_ERROR flag for this call options.')
                 ->build()
            ];
        }

        return [];
    }

    private function containsFlag(Node\Expr $expr, string $flagName): bool
    {
        if ($expr instanceof Node\Expr\ConstFetch) {
            return $expr->name->toString() === $flagName;
        }

        if ($expr instanceof Node\Expr\BinaryOp\BitwiseOr) {
            return $this->containsFlag($expr->left, $flagName) || $this->containsFlag($expr->right, $flagName);
        }

        return false;
    }
}
