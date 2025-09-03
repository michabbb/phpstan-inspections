<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis\Classes;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects autoloading issues where class names do not match file names according to PSR-0/PSR-4 standards.
 *
 * This rule identifies:
 * - PHP files with exactly one class where the class name doesn't match the file name
 * - Handles PSR-0 naming conventions (Package_Subpackage_Class)
 * - Supports WordPress naming convention (class-classname.php)
 * - Ignores certain files like index.php, actions.class.php, and Laravel migrations
 *
 * The rule ensures that autoloading works correctly by maintaining consistency
 * between class names and their containing file names.
 *
 * @implements Rule<Class_>
 */
final class AutoloadingIssuesRule implements Rule
{
    private const string MESSAGE = 'Class autoloading might be broken: file and class names are not identical.';

    /** @var array<string, bool> */
    private static array $processedFiles = [];

    /** @var array<string> */
    private static array $ignoredFiles = [
        'index.php',
        'actions.class.php',
    ];

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        $filePath = $scope->getFile();

        // Skip if already processed this file
        if (isset(self::$processedFiles[$filePath])) {
            return [];
        }

        self::$processedFiles[$filePath] = true;

        // Check if file should be ignored
        $fileName = basename($filePath);
        if ($this->shouldIgnoreFile($fileName)) {
            return [];
        }

        // Only process .php files
        if (!str_ends_with($fileName, '.php')) {
            return [];
        }

        // Read and parse the file to count classes
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return [];
        }

        $classes = $this->parseClassesFromFile($fileContent);
        if (count($classes) !== 1) {
            return [];
        }

        $className = $classes[0];
        $expectedClassName = pathinfo($fileName, PATHINFO_FILENAME);

        // Handle PSR-0 naming (extract last part after underscore if present)
        $extractedClassName = $className;
        if (str_contains($className, '_')) {
            $parts = explode('_', $className);
            $extractedClassName = end($parts);
        }

        // Check if it matches PSR-0/PSR-4 or WordPress standard
        if ($this->isBreakingPsrStandard($className, $expectedClassName, $extractedClassName)
            && !$this->isWordpressStandard($className, $fileName)) {
            return [
                RuleErrorBuilder::message(self::MESSAGE)
                    ->identifier('autoloading.classNameMismatch')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function shouldIgnoreFile(string $fileName): bool
    {
        // Check ignored files list
        if (in_array($fileName, self::$ignoredFiles, true)) {
            return true;
        }

        // Check Laravel migration pattern: YYYY_MM_DD_HHMMSS_description.php
        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.+\.php$/', $fileName)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string>
     */
    private function parseClassesFromFile(string $content): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $stmts = $parser->parse($content);

        if ($stmts === null) {
            return [];
        }

        $nodeFinder = new NodeFinder();
        $classes = $nodeFinder->findInstanceOf($stmts, Class_::class);

        $classNames = [];
        foreach ($classes as $class) {
            if ($class->name !== null) {
                $classNames[] = $class->name->toString();
            }
        }

        return $classNames;
    }

    private function isBreakingPsrStandard(string $className, string $expectedClassName, string $extractedClassName): bool
    {
        return $expectedClassName !== $extractedClassName && $expectedClassName !== $className;
    }

    private function isWordpressStandard(string $className, string $fileName): bool
    {
        $wordpressFileName = sprintf('class-%s.php', str_replace('_', '-', strtolower($className)));
        return $fileName === $wordpressFileName;
    }
}