<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects untrusted file inclusion that relies on include_path.
 *
 * This rule identifies include/require statements with relative paths that depend on the
 * include_path configuration. Such inclusions are unreliable because include_path is an
 * environment setting that can vary between deployments.
 *
 * The rule flags:
 * - Relative paths in include/require statements (e.g., 'file.php', 'subdir/file.php')
 * - Paths that don't start with '/' or drive letters (e.g., 'C:', 'D:')
 *
 * Recommended solutions:
 * - Use absolute paths with __DIR__: require_once __DIR__ . '/file.php'
 * - Use composer autoloading and namespaces instead of include/require
 * - Define APPLICATION_ROOT constant: require_once APPLICATION_ROOT . '/file.php'
 *
 * @implements Rule<Include_>
 */
class UntrustedInclusionRule implements Rule
{
    private const string MESSAGE = 'This relies on include_path and not guaranteed to load the right file. Concatenate with __DIR__ or use namespaces + class loading instead.';
    private const string ABSOLUTE_PATH_PATTERN = '/^\/|[a-z]:/i';

    public function getNodeType(): string
    {
        return Include_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Include_) {
            return [];
        }

        // Check if the argument is a string literal
        if (!$node->expr instanceof String_) {
            return [];
        }

        $path = $node->expr->value;

        // Skip empty paths
        if ($path === '') {
            return [];
        }

        // Check if path matches absolute path pattern (starts with / or drive letter)
        if (preg_match(self::ABSOLUTE_PATH_PATTERN, $path) === 1) {
            return [];
        }

        // Report the violation
        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('include.untrusted')
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}