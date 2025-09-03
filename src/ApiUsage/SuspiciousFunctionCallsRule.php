<?php

declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects suspicious function calls that compare the same value with itself.
 *
 * This rule identifies calls to comparison functions (strcmp, strncmp, strcasecmp, etc.)
 * where both arguments are the same value, which would always return 0 (equal).
 * This usually indicates a bug where different values should be compared.
 *
 * @implements Rule<Node\Expr\FuncCall>
 */
class SuspiciousFunctionCallsRule implements Rule
{
    private const string MESSAGE = 'This call compares the same string with itself, this can not be right.';
    
    /** @var string[] */
    private const array TARGET_FUNCTIONS = [
        'strcmp',
        'strncmp',
        'strcasecmp',
        'strncasecmp',
        'strnatcmp',
        'strnatcasecmp',
        'substr_compare',
        'hash_equals',
    ];

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
        
        if (!in_array($functionName, self::TARGET_FUNCTIONS, true)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 2) {
            return [];
        }

        $firstArg  = $args[0]->value;
        $secondArg = $args[1]->value;

        if ($this->areArgumentsEquivalent($firstArg, $secondArg)) {
            return [
                RuleErrorBuilder::message(self::MESSAGE)
                    ->identifier('functionCall.suspicious')
                    ->build(),
            ];
        }

        return [];
    }

    private function areArgumentsEquivalent(Node $first, Node $second): bool
    {
        return $this->nodeToString($first) === $this->nodeToString($second);
    }

    private function nodeToString(Node $node): string
    {
        if ($node instanceof Node\Scalar\String_) {
            return 'string:' . $node->value;
        }
        
        if ($node instanceof Node\Expr\Variable && is_string($node->name)) {
            return 'var:' . $node->name;
        }
        
        if ($node instanceof Node\Expr\PropertyFetch) {
            $object   = $this->nodeToString($node->var);
            $property = $node->name instanceof Node\Identifier ? $node->name->toString() : 'unknown';
            return $object . '->' . $property;
        }
        
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            $array = $this->nodeToString($node->var);
            $dim   = $node->dim ? $this->nodeToString($node->dim) : 'null';
            return $array . '[' . $dim . ']';
        }
        
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name) {
            $funcName = $node->name->toString();
            $args     = array_map(fn ($arg) => $this->nodeToString($arg->value), $node->getArgs());
            return $funcName . '(' . implode(',', $args) . ')';
        }
        
        if ($node instanceof Node\Expr\ConstFetch && $node->name instanceof Node\Name) {
            return 'const:' . $node->name->toString();
        }
        
        return get_class($node) . ':' . spl_object_hash($node);
    }
}
