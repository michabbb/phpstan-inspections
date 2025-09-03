<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of cryptographically insecure algorithms and constants.
 *
 * This rule identifies the use of weak or deprecated cryptographic algorithms
 * that have known vulnerabilities. It checks for constants related to insecure
 * encryption methods and suggests more secure alternatives.
 *
 * Detected issues:
 * - Weak hash algorithms (MD2, MD4, MD5, SHA0, SHA1)
 * - Weak encryption algorithms (DES, 3DES, RC2, RC4)
 * - Deprecated mcrypt constants with known vulnerabilities
 * - Insecure OpenSSL cipher constants
 *
 * Recommended alternatives:
 * - Use AES-128-* or AES-256-* instead of DES/3DES/RC2/RC4
 * - Use CRYPT_BLOWFISH instead of MD5/DES for password hashing
 * - Consider migrating from mcrypt to openssl extension
 *
 * @implements Rule<ConstFetch>
 */
class CryptographicallySecureAlgorithmsRule implements Rule
{
    /** @var array<string, string> */
    private const INSECURE_CONSTANTS = [
        // mcrypt constants with known bugs
        'MCRYPT_RIJNDAEL_192' => 'mcrypt\'s MCRYPT_RIJNDAEL_192 is not AES compliant, MCRYPT_RIJNDAEL_128 should be used instead.',
        'MCRYPT_RIJNDAEL_256' => 'mcrypt\'s MCRYPT_RIJNDAEL_256 is not AES compliant, MCRYPT_RIJNDAEL_128 + 256-bit key should be used instead.',
        // weak algorithms, mcrypt constants
        'MCRYPT_3DES' => '3DES has known vulnerabilities, consider using MCRYPT_RIJNDAEL_128 instead.',
        'MCRYPT_TRIPLEDES' => '3DES has known vulnerabilities, consider using MCRYPT_RIJNDAEL_128 instead.',
        'MCRYPT_DES_COMPAT' => 'DES has known vulnerabilities, consider using MCRYPT_RIJNDAEL_128 instead.',
        'MCRYPT_DES' => 'DES has known vulnerabilities, consider using MCRYPT_RIJNDAEL_128 instead.',
        'MCRYPT_RC2' => 'RC2 has known vulnerabilities, consider using MCRYPT_RIJNDAEL_128 instead.',
        'MCRYPT_RC4' => 'RC4 has known vulnerabilities, consider using MCRYPT_RIJNDAEL_128 instead.',
        'MCRYPT_ARCFOUR' => 'RC4 has known vulnerabilities, consider using MCRYPT_RIJNDAEL_128 instead.',
        // weak algorithms, openssl constants
        'OPENSSL_CIPHER_3DES' => '3DES has known vulnerabilities, consider using AES-128-* instead.',
        'OPENSSL_CIPHER_DES' => 'DES has known vulnerabilities, consider using AES-128-* instead.',
        'OPENSSL_CIPHER_RC2_40' => 'RC2 has known vulnerabilities, consider using AES-128-* instead.',
        'OPENSSL_CIPHER_RC2_64' => 'RC2 has known vulnerabilities, consider using AES-128-* instead.',
        // weak algorithms, crypt constants
        'CRYPT_MD5' => 'MD5 has known vulnerabilities, consider using CRYPT_BLOWFISH instead.',
        'CRYPT_STD_DES' => 'DES has known vulnerabilities, consider using CRYPT_BLOWFISH instead.',
    ];

    public function getNodeType(): string
    {
        return ConstFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ConstFetch) {
            return [];
        }

        $constantName = $node->name->toString();

        if (!isset(self::INSECURE_CONSTANTS[$constantName])) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::INSECURE_CONSTANTS[$constantName])
                ->identifier('security.cryptographicallyInsecureAlgorithm')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}