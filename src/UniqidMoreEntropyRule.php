<?php

namespace macropage\PHPStan\Inspections;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;

/**
 * Ensures uniqid() function uses the more_entropy parameter for better uniqueness.
 *
 * This rule detects calls to uniqid() that don't use the second parameter ($more_entropy)
 * or have it explicitly set to false. The more_entropy parameter adds additional entropy
 * to make the generated ID more unique and harder to predict.
 *
 * @implements Rule<Node\Expr\FuncCall>
 */
class UniqidMoreEntropyRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Name || strtolower($node->name->toString()) !== 'uniqid') {
            return [];
        }

        $args = $node->getArgs();

        // Check if uniqid() is called without the second $more_entropy parameter
        if (count($args) < 2) {
            return [
                RuleErrorBuilder::message(
                    "uniqid: Please provide 'more_entropy' parameter in order to increase likelihood of uniqueness."
                )->tip('Set the second parameter of uniqid() to true.')->build()
            ];
        }

        // Check if uniqid() is called with $more_entropy explicitly set to false
        $moreEntropyArgValue = $args[1]->value;
        $type = $scope->getType($moreEntropyArgValue);

        if ($type instanceof ConstantBooleanType && !$type->getValue()) {
            return [
                RuleErrorBuilder::message(
                    "uniqid: Please provide 'more_entropy' parameter in order to increase likelihood of uniqueness."
                )->tip('Set the second parameter of uniqid() to true.')->build()
            ];
        }

        return [];
    }
}
