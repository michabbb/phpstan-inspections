<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects insecure cURL SSL configuration that exposes connections to MITM attacks.
 *
 * This rule identifies usage of cURL SSL options that disable certificate verification:
 * - CURLOPT_SSL_VERIFYHOST set to values other than 2 (secure default)
 * - CURLOPT_SSL_VERIFYPEER set to values other than 1 or true (secure default)
 *
 * These insecure settings allow man-in-the-middle attacks by not properly validating
 * SSL certificates. The rule detects violations in:
 * - curl_setopt() function calls
 * - Array assignments to cURL option arrays
 *
 * @implements Rule<Node\Expr\FuncCall>
 */
class CurlSslServerSpoofingRule implements Rule
{
    private const string MESSAGE_VERIFY_HOST = 'Exposes a connection to MITM attacks. Use 2 (default) to stay safe.';
    private const string MESSAGE_VERIFY_PEER = 'Exposes a connection to MITM attacks. Use true (default) to stay safe.';

    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toString();

        // Check curl_setopt() calls
        if ($functionName === 'curl_setopt') {
            return $this->analyzeCurlSetoptCall($node, $scope);
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\RuleError>
     */
    private function analyzeCurlSetoptCall(FuncCall $node, Scope $scope): array
    {
        $args = $node->getArgs();
        if (count($args) < 3) {
            return [];
        }

        $optionArg = $args[1] ?? null;
        $valueArg = $args[2] ?? null;

        if ($optionArg === null || $valueArg === null) {
            return [];
        }

        // Check if the option is a cURL SSL constant
        $optionName = $this->extractCurlOptionName($optionArg->value);
        if ($optionName === null) {
            return [];
        }

        if ($optionName === 'CURLOPT_SSL_VERIFYHOST') {
            if ($this->isHostVerifyDisabled($valueArg->value)) {
                return [
                    RuleErrorBuilder::message(self::MESSAGE_VERIFY_HOST)
                        ->identifier('security.curl.sslVerifyHost')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        } elseif ($optionName === 'CURLOPT_SSL_VERIFYPEER') {
            if ($this->isPeerVerifyDisabled($valueArg->value)) {
                return [
                    RuleErrorBuilder::message(self::MESSAGE_VERIFY_PEER)
                        ->identifier('security.curl.sslVerifyPeer')
                        ->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function extractCurlOptionName(Node\Expr $expr): ?string
    {
        if ($expr instanceof ConstFetch && $expr->name instanceof Name) {
            $constName = $expr->name->toString();
            if (str_starts_with($constName, 'CURLOPT_SSL_')) {
                return $constName;
            }
        }

        return null;
    }

    private function isHostVerifyDisabled(Node\Expr $value): bool
    {
        $possibleValues = $this->discoverPossibleValues($value);

        if ($possibleValues === []) {
            return false;
        }

        $disableCount = 0;
        $enableCount = 0;

        foreach ($possibleValues as $possibleValue) {
            if ($possibleValue instanceof Node\Scalar\String_) {
                $isSecure = $possibleValue->value === '2';
                if ($isSecure) {
                    ++$enableCount;
                } else {
                    ++$disableCount;
                }
            } elseif ($possibleValue instanceof Node\Scalar\LNumber) {
                $isSecure = $possibleValue->value === 2;
                if ($isSecure) {
                    ++$enableCount;
                } else {
                    ++$disableCount;
                }
            } elseif ($possibleValue instanceof ConstFetch) {
                // Constants are considered disabling (not the secure default)
                ++$disableCount;
            } elseif ($possibleValue instanceof Node\Scalar) {
                // Other scalar values that aren't '2' are disabling
                $stringValue = (string) $possibleValue->value;
                if ($stringValue === '2') {
                    ++$enableCount;
                } else {
                    ++$disableCount;
                }
            }
        }

        return $disableCount > 0 && $enableCount === 0;
    }

    private function isPeerVerifyDisabled(Node\Expr $value): bool
    {
        $possibleValues = $this->discoverPossibleValues($value);

        if ($possibleValues === []) {
            return false;
        }

        $disableCount = 0;
        $enableCount = 0;

        foreach ($possibleValues as $possibleValue) {
            if ($possibleValue instanceof Node\Scalar\String_) {
                $isSecure = $possibleValue->value === '1';
                if ($isSecure) {
                    ++$enableCount;
                } else {
                    ++$disableCount;
                }
            } elseif ($possibleValue instanceof Node\Scalar\LNumber) {
                $isSecure = $possibleValue->value === 1;
                if ($isSecure) {
                    ++$enableCount;
                } else {
                    ++$disableCount;
                }
            } elseif ($possibleValue instanceof ConstFetch && $possibleValue->name instanceof Name) {
                $constName = $possibleValue->name->toString();
                $isSecure = $constName === 'true';
                if ($isSecure) {
                    ++$enableCount;
                } else {
                    ++$disableCount;
                }
            } elseif ($possibleValue instanceof Node\Scalar) {
                // Other scalar values that aren't '1' are disabling
                $stringValue = (string) $possibleValue->value;
                if ($stringValue === '1') {
                    ++$enableCount;
                } else {
                    ++$disableCount;
                }
            }
        }

        return $disableCount > 0 && $enableCount === 0;
    }

    /**
     * @return list<Node\Expr>
     */
    private function discoverPossibleValues(Node\Expr $expr): array
    {
        $values = [];

        if ($expr instanceof Node\Scalar) {
            $values[] = $expr;
        } elseif ($expr instanceof ConstFetch) {
            $values[] = $expr;
        } elseif ($expr instanceof Node\Expr\Ternary) {
            // Handle ternary expressions by collecting values from both branches
            $values = array_merge(
                $values,
                $this->discoverPossibleValues($expr->if),
                $this->discoverPossibleValues($expr->else)
            );
        }

        return $values;
    }
}