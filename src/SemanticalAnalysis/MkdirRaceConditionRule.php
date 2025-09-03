<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\SemanticalAnalysis;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects 'mkdir(...)' race condition vulnerabilities.
 *
 * This rule identifies unsafe mkdir() usage patterns that can lead to race conditions
 * when multiple processes attempt to create the same directory simultaneously.
 *
 * The rule detects:
 * - Direct mkdir() calls without proper existence checks
 * - mkdir() calls in if statements missing is_dir() checks
 * - mkdir() calls in binary expressions (&& or ||) without is_dir() verification
 *
 * Recommended pattern:
 * if (!is_dir($directory) && !mkdir($directory) && !is_dir($directory)) {
 *     throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
 * }
 *
 * @implements Rule<FuncCall>
 */
class MkdirRaceConditionRule implements Rule
{
    private const string MESSAGE_DIRECT_CALL = "Following construct should be used: 'if (!mkdir(%s) && !is_dir(...)) { ... }'.";
    private const string MESSAGE_MISSING_AND_CHECK = "Some check are missing: '!mkdir(%s) && !is_dir(...)'.";
    private const string MESSAGE_MISSING_OR_CHECK = "Some check are missing: 'mkdir(%s) || is_dir(...)'.";

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        // Check if this is a mkdir function call
        if (!$this->isMkdirCall($node)) {
            return [];
        }

        // Skip test contexts and non-root namespace calls (similar to Java inspector)
        if ($this->shouldSkipCall($scope)) {
            return [];
        }

        $arguments = $this->extractArguments($node);
        $context = $this->findExpressionContext($node);

        if ($context === null) {
            // Direct call - suggest proper construct
            $message = sprintf(self::MESSAGE_DIRECT_CALL, $arguments);
            return [
                RuleErrorBuilder::message($message)
                    ->identifier('mkdir.raceCondition')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        if ($context instanceof If_) {
            return $this->analyzeIfContext($node, $context, $arguments);
        }

        if ($context instanceof BinaryOp) {
            return $this->analyzeBinaryContext($node, $context, $arguments);
        }

        return [];
    }

    private function isMkdirCall(FuncCall $node): bool
    {
        if ($node->name instanceof Node\Name) {
            return $node->name->toString() === 'mkdir';
        }
        return false;
    }

    private function shouldSkipCall(Scope $scope): bool
    {
        // Skip if not in root namespace (similar to Java inspector)
        $namespace = $scope->getNamespace();
        if ($namespace !== null && $namespace !== '') {
            return true;
        }

        // Skip test contexts - check if we're in a test file or class
        $filePath = $scope->getFile();
        if (str_contains($filePath, '/tests/') ||
            str_contains($filePath, '/Test/') ||
            str_contains($filePath, 'Test.php')) {
            return true;
        }

        return false;
    }

    private function findExpressionContext(FuncCall $node): ?Node
    {
        $current = $node->getAttribute('parent');

        while ($current !== null) {
            if ($current instanceof If_ || $current instanceof BinaryOp) {
                return $current;
            }

            // Stop at statement boundaries
            if ($current instanceof Node\Stmt) {
                return null; // Direct call in statement
            }

            $current = $current->getAttribute('parent');
        }

        return null;
    }

    private function analyzeIfContext(FuncCall $node, If_ $context, string $arguments): array
    {
        // Check if there's an is_dir check in the condition
        $hasIsDirCheck = $this->hasIsDirCheck($context->cond);

        if (!$hasIsDirCheck) {
            $message = sprintf(self::MESSAGE_MISSING_AND_CHECK, $arguments);
            return [
                RuleErrorBuilder::message($message)
                    ->identifier('mkdir.raceCondition')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function analyzeBinaryContext(FuncCall $node, BinaryOp $context, string $arguments): array
    {
        // Check if there's an is_dir check in the binary expression
        $hasIsDirCheck = $this->hasIsDirCheck($context);

        if (!$hasIsDirCheck) {
            $message = $context instanceof BinaryOp\BooleanAnd
                ? sprintf(self::MESSAGE_MISSING_AND_CHECK, $arguments)
                : sprintf(self::MESSAGE_MISSING_OR_CHECK, $arguments);

            return [
                RuleErrorBuilder::message($message)
                    ->identifier('mkdir.raceCondition')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    private function hasIsDirCheck(Node $node): bool
    {
        $finder = new NodeFinder();

        $isDirCalls = $finder->find($node, static function (Node $subNode): bool {
            return $subNode instanceof FuncCall
                && $subNode->name instanceof Node\Name
                && $subNode->name->toString() === 'is_dir';
        });

        return !empty($isDirCalls);
    }

    private function extractArguments(FuncCall $node): string
    {
        $args = [];
        foreach ($node->args as $arg) {
            // Try to get a readable representation of the argument
            if (isset($arg->value)) {
                $argText = $arg->value->getAttribute('rawText');
                if ($argText !== null) {
                    $args[] = $argText;
                } else {
                    $args[] = '...';
                }
            }
        }

        return implode(', ', $args);
    }
}