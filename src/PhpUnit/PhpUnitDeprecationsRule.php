<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\PhpUnit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects deprecated PHPUnit API usage patterns.
 *
 * This rule identifies deprecated PHPUnit assertion methods and arguments that should be replaced
 * with their modern equivalents. It helps maintain compatibility with newer PHPUnit versions and
 * follows current best practices for testing.
 *
 * Detected deprecations:
 * - assertEquals/assertNotEquals with $delta argument (use assertEqualsWithDelta/assertNotEqualsWithDelta)
 * - assertEquals/assertNotEquals with $maxDepth argument (deprecated since PHPUnit 8.0)
 * - assertEquals/assertNotEquals with $canonicalize argument (use assertEqualsCanonicalizing/assertNotEqualsCanonicalizing)
 * - assertEquals/assertNotEquals with $ignoreCase argument (use assertEqualsIgnoringCase/assertNotEqualsIgnoringCase)
 * - assertFileNotExists/assertDirectoryNotExists (use assertFileDoesNotExist/assertDirectoryDoesNotExist)
 *
 * @implements Rule<MethodCall>
 */
final class PhpUnitDeprecationsRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        $errors = [];

        // Check for deprecated assertEquals/assertNotEquals arguments
        $errors = array_merge($errors, $this->checkAssertEqualsArguments($node, $scope));

        // Check for deprecated file/directory assertion method names
        $errors = array_merge($errors, $this->checkFileAssertionMethods($node, $scope));

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function checkAssertEqualsArguments(MethodCall $node, Scope $scope): array
    {
        $errors = [];

        if (!$this->isMethodName($node, ['assertEquals', 'assertNotEquals'])) {
            return $errors;
        }

        $args = $node->getArgs();
        $methodName = $this->getMethodName($node);

        if (count($args) > 3) {
            // 4th argument: $delta (deprecated, use assertEqualsWithDelta/assertNotEqualsWithDelta)
            if (count($args) >= 4) {
                $replacement = str_replace('assert', 'assert', $methodName) . 'WithDelta';
                $errors[] = RuleErrorBuilder::message(
                    sprintf('$delta is deprecated in favor of %s() since PHPUnit 8.0.', $replacement)
                )
                    ->identifier('phpunit.deprecatedArgument')
                    ->line($args[3]->getStartLine())
                    ->build();
            }

            // 5th argument: $maxDepth (deprecated since PHPUnit 8.0)
            if (count($args) >= 5) {
                $errors[] = RuleErrorBuilder::message(
                    '$maxDepth is deprecated since PHPUnit 8.0.'
                )
                    ->identifier('phpunit.deprecatedArgument')
                    ->line($args[4]->getStartLine())
                    ->build();
            }

            // 6th argument: $canonicalize (deprecated, use assertEqualsCanonicalizing/assertNotEqualsCanonicalizing)
            if (count($args) >= 6) {
                $replacement = str_replace('assert', 'assert', $methodName) . 'Canonicalizing';
                $errors[] = RuleErrorBuilder::message(
                    sprintf('$canonicalize is deprecated in favor of %s() since PHPUnit 8.0.', $replacement)
                )
                    ->identifier('phpunit.deprecatedArgument')
                    ->line($args[5]->getStartLine())
                    ->build();
            }

            // 7th argument: $ignoreCase (deprecated, use assertEqualsIgnoringCase/assertNotEqualsIgnoringCase)
            if (count($args) >= 7) {
                $replacement = str_replace('assert', 'assert', $methodName) . 'IgnoringCase';
                $errors[] = RuleErrorBuilder::message(
                    sprintf('$ignoreCase is deprecated in favor of %s() since PHPUnit 8.0.', $replacement)
                )
                    ->identifier('phpunit.deprecatedArgument')
                    ->line($args[6]->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<RuleError>
     */
    private function checkFileAssertionMethods(MethodCall $node, Scope $scope): array
    {
        $errors = [];

        if (!$this->isMethodName($node, ['assertFileNotExists', 'assertDirectoryNotExists'])) {
            return $errors;
        }

        $methodName = $this->getMethodName($node);
        $replacement = str_replace('NotExist', 'DoesNotExist', $methodName);

        $errors[] = RuleErrorBuilder::message(
            sprintf('%s is deprecated in favor of %s() since PHPUnit 9.1.', $methodName, $replacement)
        )
            ->identifier('phpunit.deprecatedMethod')
            ->line($node->getStartLine())
            ->build();

        return $errors;
    }

    private function isMethodName(MethodCall $node, array|string $expectedNames): bool
    {
        $methodName = $this->getMethodName($node);
        if ($methodName === null) {
            return false;
        }

        $names = is_array($expectedNames) ? $expectedNames : [$expectedNames];
        return in_array($methodName, $names, true);
    }

    private function getMethodName(MethodCall $node): ?string
    {
        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        return $node->name->name;
    }
}