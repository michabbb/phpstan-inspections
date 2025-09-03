<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects security vulnerabilities in Composer dependencies and improper package placement.
 *
 * This rule analyzes composer.json files to ensure:
 * - roave/security-advisories is included in require-dev with version dev-latest
 * - Development packages are placed in require-dev instead of require section
 * - Only applies to non-library projects (type != "library")
 *
 * It helps prevent vulnerable components from being installed by enforcing
 * the use of security advisories as a firewall for Composer packages.
 *
 * @implements Rule<FuncCall>
 */
final class SecurityAdvisoriesRule implements Rule
{
    private const DEVELOPMENT_PACKAGES = [
        'phpunit/phpunit',
        'johnkary/phpunit-speedtrap',
        'brianium/paratest',
        'mybuilder/phpunit-accelerator',
        'codedungeon/phpunit-result-printer',
        'spatie/phpunit-watcher',
        'symfony/phpunit-bridge',
        'symfony/debug',
        'symfony/var-dumper',
        'symfony/maker-bundle',
        'zendframework/zend-test',
        'zendframework/zend-debug',
        'yiisoft/yii2-gii',
        'yiisoft/yii2-debug',
        'orchestra/testbench',
        'barryvdh/laravel-debugbar',
        'codeception/codeception',
        'behat/behat',
        'phpspec/prophecy',
        'phpspec/phpspec',
        'humbug/humbug',
        'infection/infection',
        'mockery/mockery',
        'satooshi/php-coveralls',
        'mikey179/vfsStream',
        'filp/whoops',
        'friendsofphp/php-cs-fixer',
        'phpstan/phpstan',
        'vimeo/psalm',
        'jakub-onderka/php-parallel-lint',
        'squizlabs/php_codesniffer',
        'slevomat/coding-standard',
        'doctrine/coding-standard',
        'phpcompatibility/php-compatibility',
        'zendframework/zend-coding-standard',
        'yiisoft/yii2-coding-standards',
        'wp-coding-standards/wpcs',
        'phpmd/phpmd',
        'pdepend/pdepend',
        'sebastian/phpcpd',
        'povils/phpmnd',
        'phan/phan',
        'phpro/grumphp',
        'wimg/php-compatibility',
        'sstalle/php7cc',
        'phing/phing',
        'composer/composer',
        'roave/security-advisories',
        'kalessil/production-dependencies-guard',
    ];

    private bool $hasCheckedComposerJson = false;

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Only check once per analysis run to avoid duplicate errors
        if ($this->hasCheckedComposerJson) {
            return [];
        }

        $this->hasCheckedComposerJson = true;

        $composerJsonPath = $this->findComposerJsonPath($scope);
        if ($composerJsonPath === null) {
            return [];
        }

        $errors = [];
        $composerData = $this->parseComposerJson($composerJsonPath);
        if ($composerData === null) {
            return [];
        }

        // Skip libraries
        if ($this->isLibrary($composerData)) {
            return [];
        }

        // Check for misplaced development packages
        $misplacedPackages = $this->checkMisplacedPackages($composerData);
        foreach ($misplacedPackages as $packageName => $line) {
            $errors[] = RuleErrorBuilder::message(
                "Dev-packages have no security guarantees, invoke the package via require-dev instead."
            )
                ->identifier('composer.devPackageInRequire')
                ->line($line)
                ->build();
        }

        // Check for missing or incorrect security advisories
        $advisoriesError = $this->checkSecurityAdvisories($composerData);
        if ($advisoriesError !== null) {
            $errors[] = $advisoriesError;
        }

        return $errors;
    }

    private function findComposerJsonPath(Scope $scope): ?string
    {
        $filePath = $scope->getFile();
        $directory = dirname($filePath);

        // Look for composer.json in the current directory and parent directories
        while ($directory !== '/' && $directory !== '.' && $directory !== '') {
            $composerPath = $directory . '/composer.json';
            if (file_exists($composerPath)) {
                return $composerPath;
            }
            $directory = dirname($directory);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseComposerJson(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $composerData
     */
    private function isLibrary(array $composerData): bool
    {
        $type = $composerData['type'] ?? null;
        return $type === 'library';
    }

    /**
     * @param array<string, mixed> $composerData
     * @return array<string, int>
     */
    private function checkMisplacedPackages(array $composerData): array
    {
        $misplaced = [];
        $require = $composerData['require'] ?? [];

        if (!is_array($require)) {
            return $misplaced;
        }

        foreach ($require as $packageName => $version) {
            if (is_string($packageName) && in_array(strtolower($packageName), self::DEVELOPMENT_PACKAGES, true)) {
                // For simplicity, we'll use line 1 since we can't easily determine exact line numbers in JSON
                $misplaced[$packageName] = 1;
            }
        }

        return $misplaced;
    }

    /**
     * @param array<string, mixed> $composerData
     */
    private function checkSecurityAdvisories(array $composerData): ?RuleError
    {
        $requireDev = $composerData['require-dev'] ?? [];
        $require = $composerData['require'] ?? [];

        if (!is_array($requireDev) || !is_array($require)) {
            return null;
        }

        $hasThirdPartyPackages = $this->hasThirdPartyPackages($composerData);
        $hasSecurityAdvisories = false;
        $advisoriesVersionCorrect = false;

        // Check require-dev for security advisories
        foreach ($requireDev as $packageName => $version) {
            if (is_string($packageName) && strtolower($packageName) === 'roave/security-advisories') {
                $hasSecurityAdvisories = true;
                if (is_string($version) && strtolower($version) === 'dev-latest') {
                    $advisoriesVersionCorrect = true;
                }
                break;
            }
        }

        // Also check require section for security advisories (should be in require-dev)
        foreach ($require as $packageName => $version) {
            if (is_string($packageName) && strtolower($packageName) === 'roave/security-advisories') {
                return RuleErrorBuilder::message(
                    "Please use dev-latest instead."
                )
                    ->identifier('composer.securityAdvisoriesVersion')
                    ->line(1)
                    ->build();
            }
        }

        if (!$hasSecurityAdvisories && $hasThirdPartyPackages) {
            return RuleErrorBuilder::message(
                "Please add roave/security-advisories:dev-latest into require-dev as a firewall for vulnerable components."
            )
                ->identifier('composer.missingSecurityAdvisories')
                ->line(1)
                ->build();
        }

        if ($hasSecurityAdvisories && !$advisoriesVersionCorrect) {
            return RuleErrorBuilder::message(
                "Please use dev-latest instead."
            )
                ->identifier('composer.securityAdvisoriesVersion')
                ->line(1)
                ->build();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $composerData
     */
    private function hasThirdPartyPackages(array $composerData): bool
    {
        $require = $composerData['require'] ?? [];
        $name = $composerData['name'] ?? null;

        if (!is_array($require) || !is_string($name)) {
            return false;
        }

        $vendorPrefix = $this->extractVendorPrefix($name);

        foreach ($require as $packageName => $version) {
            if (!is_string($packageName)) {
                continue;
            }

            $packageLower = strtolower($packageName);
            if (strpos($packageLower, '/') === false) {
                continue; // Not a third-party package
            }

            if ($vendorPrefix !== null && str_starts_with($packageLower, $vendorPrefix)) {
                continue; // Same vendor
            }

            return true; // Found third-party package
        }

        return false;
    }

    private function extractVendorPrefix(string $packageName): ?string
    {
        $pos = strpos($packageName, '/');
        if ($pos === false) {
            return null;
        }

        return strtolower(substr($packageName, 0, $pos + 1));
    }
}