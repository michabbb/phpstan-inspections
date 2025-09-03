<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects insecure initialization vector (IV) generation for encryption functions.
 *
 * This rule identifies when openssl_encrypt() or mcrypt_encrypt() is called with
 * an initialization vector that is not generated using cryptographically secure
 * random functions. The IV should be generated using:
 * - random_bytes() for general use
 * - openssl_random_pseudo_bytes() for OpenSSL functions
 * - mcrypt_create_iv() for Mcrypt functions
 *
 * @implements Rule<FuncCall>
 */
class EncryptionInitializationVectorRandomnessRule implements Rule
{
    public const string SECURE_FUNCTIONS = 'secure_functions';

    /** @var array<string, true> */
    private array $secureFunctions;

    public function __construct()
    {
        $this->secureFunctions = [
            'random_bytes' => true,
            'openssl_random_pseudo_bytes' => true,
            'mcrypt_create_iv' => true,
        ];
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        if (!$node->name instanceof Node\Name) {
            return [];
        }

        $functionName = $node->name->toString();

        // Only check openssl_encrypt and mcrypt_encrypt
        if ($functionName !== 'openssl_encrypt' && $functionName !== 'mcrypt_encrypt') {
            return [];
        }

        // Check if there are enough arguments (need at least 5 for the IV parameter)
        if (count($node->args) < 5) {
            return [];
        }

        $ivArg = $node->args[4];
        if ($ivArg === null || $ivArg->value === null) {
            return [];
        }

        // Analyze the IV argument to see if it comes from a secure source
        $insecureSources = $this->findInsecureIvSources($ivArg->value, $scope);

        if ($insecureSources === []) {
            return [];
        }

        // Determine the recommended secure function based on the encryption function
        $recommendedFunction = $functionName === 'openssl_encrypt'
            ? 'openssl_random_pseudo_bytes'
            : 'mcrypt_create_iv';

        $message = sprintf(
            '%s() should be used for IV, but found: %s.',
            $recommendedFunction,
            implode(', ', $insecureSources)
        );

        return [
            RuleErrorBuilder::message($message)
                ->identifier('security.encryptionIvRandomness')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * Analyzes the IV argument to find insecure sources.
     *
     * @return list<string>
     */
    private function findInsecureIvSources(Node\Expr $ivExpr, Scope $scope): array
    {
        $insecureSources = [];

        // Check if it's a direct function call
        if ($ivExpr instanceof FuncCall && $ivExpr->name instanceof Node\Name) {
            $calledFunction = $ivExpr->name->toString();
            if (isset($this->secureFunctions[$calledFunction])) {
                // This is a secure function call, so no insecure source
                return [];
            } else {
                // This is an insecure function call
                $insecureSources[] = $calledFunction . '()';
            }
            return $insecureSources;
        }

        // For other expressions, we consider them insecure unless we can prove they're from secure sources
        // This is a simplified approach - in a full implementation, we'd need more sophisticated
        // analysis of variable assignments, constants, etc.
        if ($ivExpr instanceof Node\Expr\Variable) {
            // For variables, we can't easily track their source in this simple implementation
            // In a real-world scenario, we'd need to use PHPStan's data flow analysis
            $insecureSources[] = '$' . $ivExpr->name;
        } elseif ($ivExpr instanceof Node\Scalar\String_) {
            $insecureSources[] = '"' . $ivExpr->value . '"';
        } elseif ($ivExpr instanceof Node\Scalar\LNumber) {
            $insecureSources[] = (string) $ivExpr->value;
        } else {
            // Other expressions (arrays, concatenations, etc.)
            $insecureSources[] = $this->getExpressionText($ivExpr);
        }

        return $insecureSources;
    }

    /**
     * Gets a textual representation of an expression for error messages.
     */
    private function getExpressionText(Node\Expr $expr): string
    {
        // This is a simplified approach - in practice, we'd want more sophisticated
        // expression serialization
        if ($expr instanceof Node\Expr\Array_) {
            return 'array(...)';
        }
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return '... . ...';
        }

        return 'expression';
    }
}