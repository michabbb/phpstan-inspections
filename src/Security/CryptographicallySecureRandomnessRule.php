<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\UnaryOp\Not;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Expr\ConstFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PhpParser\NodeFinder;

/**
 * Detects insecure random number generation and suggests cryptographically secure alternatives.
 *
 * This rule checks for usage of insecure random functions like openssl_random_pseudo_bytes()
 * and mcrypt_create_iv(), suggesting to use random_bytes() instead. It also verifies that
 * proper error checking is in place for cryptographic operations.
 *
 * @implements \PHPStan\Rules\Rule<FuncCall>
 */
final class CryptographicallySecureRandomnessRule implements Rule
{
    private const string MESSAGE_USE_RANDOM_BYTES = 'Consider using cryptographically secure random_bytes() instead.';
    private const string MESSAGE_VERIFY_BYTES = 'The IV generated can be false, please add necessary checks.';
    private const string MESSAGE_OPENSSL_2ND_ARGUMENT_NOT_DEFINED = 'Use 2nd parameter for determining if the algorithm used was cryptographically strong.';
    private const string MESSAGE_MCRYPT_2ND_ARGUMENT_NOT_DEFINED = 'Please provide 2nd parameter implicitly as default value has changed between PHP versions.';
    private const string MESSAGE_OPENSSL_2ND_ARGUMENT_NOT_VERIFIED = '$crypto_strong can be false, please add necessary checks.';
    private const string MESSAGE_MCRYPT_2ND_ARGUMENT_NOT_SECURE = 'It\'s better to use MCRYPT_DEV_RANDOM here (may block until more entropy is available).';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        $functionName = $this->resolveFunctionName($node, $scope);
        if ($functionName === null) {
            return [];
        }

        $errors = [];
        $isOpenssl = $functionName === 'openssl_random_pseudo_bytes';
        $isMcrypt = $functionName === 'mcrypt_create_iv';

        if (!$isOpenssl && !$isMcrypt) {
            return [];
        }

        // Case 1: use random_bytes in PHP7+
        // For this project, we assume PHP 7+ is the baseline.
        $errors[] = RuleErrorBuilder::message(self::MESSAGE_USE_RANDOM_BYTES)
            ->identifier('security.randomBytesSuggestion')
            ->line($node->getStartLine())
            ->build();

        $arguments = $node->getArgs();
        $hasSecondArgument = count($arguments) >= 2;

        // Case 2: report missing 2nd argument
        if (!$hasSecondArgument) {
            $message = $isOpenssl ? self::MESSAGE_OPENSSL_2ND_ARGUMENT_NOT_DEFINED : self::MESSAGE_MCRYPT_2ND_ARGUMENT_NOT_DEFINED;
            $errors[] = RuleErrorBuilder::message($message)
                ->identifier('security.missingSecondArgument')
                ->line($node->getStartLine())
                ->build();
        }

        // Case 3: unchecked generation result
        // This is a simplified check compared to the Java inspector's deep AST traversal.
        // We check if the function call is assigned to a variable and if that variable is immediately checked for false.
        $isResultVerified = $this->isResultCheckedForFalse($node, $scope);
        if (!$isResultVerified) {
            $errors[] = RuleErrorBuilder::message(self::MESSAGE_VERIFY_BYTES)
                ->identifier('security.uncheckedReturnValue')
                ->line($node->getStartLine())
                ->build();
        }

        // Case 4: is 2nd argument verified/strong enough
        if ($hasSecondArgument) {
            $secondArgument = $arguments[1]->value;

            if ($isMcrypt) {
                $reliableSource = false;
                if ($secondArgument instanceof ConstFetch) {
                    $constantName = (string) $secondArgument->name;
                    if (strcasecmp($constantName, 'MCRYPT_DEV_RANDOM') === 0) {
                        $reliableSource = true;
                    }
                }
                if (!$reliableSource) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_MCRYPT_2ND_ARGUMENT_NOT_SECURE)
                        ->identifier('security.mcryptInsecureArgument')
                        ->line($secondArgument->getStartLine())
                        ->build();
                }
            } elseif ($isOpenssl) {
                // Check if the $crypto_strong variable (second argument) is checked for false
                // This is also a simplified check.
                if (!$this->isArgumentCheckedForFalse($secondArgument, $scope)) {
                    $errors[] = RuleErrorBuilder::message(self::MESSAGE_OPENSSL_2ND_ARGUMENT_NOT_VERIFIED)
                        ->identifier('security.opensslArgumentNotVerified')
                        ->line($secondArgument->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }

    private function resolveFunctionName(FuncCall $node, Scope $scope): ?string
    {
        if ($node->name instanceof Node\Name) {
            return (string) $node->name;
        }

        return null;
    }

    /**
     * Simplified check to see if the result of the function call is checked for false.
     * This will look for immediate assignment and comparison with false.
     */
    private function isResultCheckedForFalse(FuncCall $funcCall, Scope $scope): bool
    {
        $parentNode = $funcCall->getAttribute('parent');

        // Case: $var = func_call(); if ($var === false) { ... }
        if ($parentNode instanceof Assign) {
            $assignedVariable = $parentNode->var;
            if ($assignedVariable instanceof Variable) {
                // Look for immediate usage in a comparison with false
                $nodeFinder = new NodeFinder();
                $functionScopeNode = $scope->getFunction(); // Get the current function/method node

                if ($functionScopeNode === null) {
                    // If not in a function/method, assume it's checked to avoid false positives in global scope
                    return true;
                }

                // Search within the current function/method for comparisons involving the assigned variable
                $found = $nodeFinder->find($functionScopeNode, static fn(Node $node) =>
                    ($node instanceof Identical || $node instanceof NotIdentical)
                    && (
                        (($node->left instanceof ConstFetch && strcasecmp((string)$node->left->name, 'false') === 0) && $this->areNodesEquivalent($node->right, $assignedVariable))
                        || (($node->right instanceof ConstFetch && strcasecmp((string)$node->right->name, 'false') === 0) && $this->areNodesEquivalent($node->left, $assignedVariable))
                    )
                );

                if (count($found) > 0) {
                    return true;
                }

                // Search for negation: if (!$var) { ... }
                $foundNegation = $nodeFinder->find($functionScopeNode, static fn(Node $node) =>
                    $node instanceof Not
                    && $this->areNodesEquivalent($node->expr, $assignedVariable)
                );

                if (count($foundNegation) > 0) {
                    return true;
                }
            }
        }

        // Case: if (func_call() === false) { ... } or if (!func_call()) { ... }
        if ($parentNode instanceof Identical || $parentNode instanceof NotIdentical) {
            if (($parentNode->left instanceof ConstFetch && strcasecmp((string)$parentNode->left->name, 'false') === 0) ||
                ($parentNode->right instanceof ConstFetch && strcasecmp((string)$parentNode->right->name, 'false') === 0)) {
                return true;
            }
        } elseif ($parentNode instanceof Not) {
            return true;
        }

        return false;
    }

    /**
     * Simplified check to see if a specific argument (variable) is checked for false.
     */
    private function isArgumentCheckedForFalse(Node $argumentNode, Scope $scope): bool
    {
        if (!$argumentNode instanceof Variable) {
            return true; // Not a variable, so we can't check it for false in subsequent code. Assume it's fine or not applicable.
        }

        $nodeFinder = new NodeFinder();
        $functionScopeNode = $scope->getFunction();

        if ($functionScopeNode === null) {
            return true; // Assume checked if not in a function/method
        }

        // Search within the current function/method for comparisons involving the argument variable
        $found = $nodeFinder->find($functionScopeNode, static fn(Node $node) =>
            ($node instanceof Identical || $node instanceof NotIdentical)
            && (
                (($node->left instanceof ConstFetch && strcasecmp((string)$node->left->name, 'false') === 0) && $this->areNodesEquivalent($node->right, $argumentNode))
                || (($node->right instanceof ConstFetch && strcasecmp((string)$node->right->name, 'false') === 0) && $this->areNodesEquivalent($node->left, $argumentNode))
            )
        );

        if (count($found) > 0) {
            return true;
        }

        // Search for negation: if (!$var) { ... }
        $foundNegation = $nodeFinder->find($functionScopeNode, static fn(Node $node) =>
            $node instanceof Not
            && $this->areNodesEquivalent($node->expr, $argumentNode)
        );

        if (count($foundNegation) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Helper to compare two nodes for equivalence.
     * This is a basic comparison and might need to be more robust for complex scenarios.
     */
    private function areNodesEquivalent(Node $node1, Node $node2): bool
    {
        if ($node1 instanceof Variable && $node2 instanceof Variable) {
            return $node1->name === $node2->name;
        }
        // Add more comparison logic if needed for other node types
        return false;
    }
}
