<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of short PHP open tags '<?' and suggests using '<?php' instead.
 *
 * This rule identifies PHP files that use the short open tag '<?' which is considered
 * a bad practice. The short tag can cause issues when short_open_tag is disabled
 * in php.ini, making code less portable.
 *
 * The rule detects:
 * - Files starting with '<?' instead of '<?php'
 * - Suggests replacing '<?' with '<?php' for better compatibility
 *
 * @implements Rule<Stmt>
 */
class ShortOpenTagUsageRule implements Rule
{
    /** @var array<string, bool> */
    private static array $processedFiles = [];

    public function getNodeType(): string
    {
        return Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Skip if already processed this file
        if (isset(self::$processedFiles[$file])) {
            return [];
        }

        self::$processedFiles[$file] = true;

        // Read the file content
        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        // Check if file starts with short tag
        if (str_starts_with($content, '<?')) {
            // Check if it's not already '<?php'
            $lines = explode("\n", $content, 2);
            $firstLine = $lines[0] ?? '';

            if (str_starts_with($firstLine, '<?') && !str_starts_with($firstLine, '<?php')) {
                return [
                    RuleErrorBuilder::message("Using the '<?' short tag is considered bad practice. Use '<?php' instead.")
                        ->identifier('php.shortOpenTag')
                        ->line(1)
                        ->build(),
                ];
            }
        }

        return [];
    }
}