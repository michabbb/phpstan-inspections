<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Deprecations;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of deprecated random API functions and suggests modern alternatives.
 *
 * This rule identifies calls to outdated random number generation functions and recommends
 * their cryptographically secure replacements:
 * - srand() → mt_srand()
 * - getrandmax() → mt_getrandmax()
 * - rand() → random_int() (with 2 parameters) or mt_rand() (with 1 parameter)
 * - mt_rand() → random_int() (with 2 parameters)
 *
 * The rule assumes PHP 7.0+ and suggests random_int() where appropriate for cryptographic security.
 *
 * @implements Rule<FuncCall>
 */
final class RandomApiMigrationRule implements Rule
{
    private const string MESSAGE_PATTERN = "'%s(...)' has recommended replacement '%s(...)', consider migrating.";

    /**
     * @var array<string, string>
     */
    private const array MAPPINGS = [
        'srand' => 'mt_srand',
        'getrandmax' => 'mt_getrandmax',
        'rand' => 'random_int',
        'mt_rand' => 'random_int',
    ];

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

        if (!array_key_exists($functionName, self::MAPPINGS)) {
            return [];
        }

        $suggestedFunction = self::MAPPINGS[$functionName];
        $arguments = $node->getArgs();

        // Special handling for random_int: it requires exactly 2 parameters
        if ($suggestedFunction === 'random_int') {
            $argumentCount = count($arguments);

            // If we don't have exactly 2 parameters for random_int, suggest mt_rand instead
            if ($argumentCount !== 2) {
                if ($functionName === 'rand') {
                    $suggestedFunction = 'mt_rand';
                } else {
                    // For mt_rand and other functions: return early, don't suggest anything
                    // This matches the Java original behavior (line 84: return;)
                    return [];
                }
            }
        }

        $message = sprintf(self::MESSAGE_PATTERN, $functionName, $suggestedFunction);

        return [
            RuleErrorBuilder::message($message)
                ->identifier('random.deprecatedApi')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function resolveFunctionName(FuncCall $node, Scope $scope): ?string
    {
        if ($node->name instanceof Node\Name) {
            return (string) $node->name;
        }

        return null;
    }
}