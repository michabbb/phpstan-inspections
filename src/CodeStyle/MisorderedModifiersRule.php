<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects misordered modifiers in method declarations according to PSR standards.
 *
 * This rule identifies method declarations where the order of modifiers (final, abstract,
 * visibility keywords, static) does not follow the PSR-compliant order. The correct order is:
 * - final
 * - abstract
 * - public/protected/private
 * - static
 *
 * Only methods that contain at least one of the modifiers (static, abstract, final) are checked,
 * as methods without these modifiers cannot have ordering issues.
 *
 * @implements Rule<ClassMethod>
 */
final class MisorderedModifiersRule implements Rule
{
    /**
     * @var array<string, int>
     */
    private const MODIFIER_ORDER = [
        'final' => 1,
        'abstract' => 2,
        'public' => 3,
        'protected' => 3,
        'private' => 3,
        'static' => 4,
    ];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof ClassMethod) {
            return [];
        }

        // Only check methods that have at least one relevant modifier
        if (!$node->isStatic() && !$node->isAbstract() && !$node->isFinal()) {
            return [];
        }

        $modifiers = $this->extractModifiers($node);
        if (count($modifiers) < 2) {
            return [];
        }

        $currentOrder = $this->getCurrentOrder($modifiers);
        $expectedOrder = $this->getExpectedOrder($modifiers);

        // Get the source code and extract modifiers in lexical order
        $filePath = $scope->getFile();
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath);
        $line = $lines[$node->getStartLine() - 1] ?? '';
        $sourceModifiers = $this->extractModifiersFromSource($line);

        if (count($sourceModifiers) < 2) {
            return [];
        }

        $currentOrder = implode(' ', $sourceModifiers);
        $expectedOrder = $this->getExpectedOrder($sourceModifiers);

        if ($currentOrder !== $expectedOrder) {
            return [
                RuleErrorBuilder::message('Modifiers are misordered (according to PSRs)')
                    ->identifier('method.modifiersOrder')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }

    /**
     * @param ClassMethod $node
     * @return array<string>
     */
    private function extractModifiers(ClassMethod $node): array
    {
        $modifiers = [];

        if ($node->isFinal()) {
            $modifiers[] = 'final';
        }
        if ($node->isAbstract()) {
            $modifiers[] = 'abstract';
        }
        if ($node->isPublic()) {
            $modifiers[] = 'public';
        }
        if ($node->isProtected()) {
            $modifiers[] = 'protected';
        }
        if ($node->isPrivate()) {
            $modifiers[] = 'private';
        }
        if ($node->isStatic()) {
            $modifiers[] = 'static';
        }

        return $modifiers;
    }

    /**
     * @param array<string> $modifiers
     * @return string
     */
    private function getCurrentOrder(array $modifiers): string
    {
        return implode(' ', $modifiers);
    }

    /**
     * @param array<string> $modifiers
     * @return string
     */
    private function getExpectedOrder(array $modifiers): string
    {
        // Sort modifiers by their defined order
        usort($modifiers, function (string $a, string $b): int {
            $orderA = self::MODIFIER_ORDER[$a] ?? 999;
            $orderB = self::MODIFIER_ORDER[$b] ?? 999;
            return $orderA <=> $orderB;
        });

        return implode(' ', $modifiers);
    }

    /**
     * Extract modifiers from source code line
     * @param string $line
     * @return array<string>
     */
    private function extractModifiersFromSource(string $line): array
    {
        $modifiers = [];
        $keywords = ['final', 'abstract', 'public', 'protected', 'private', 'static'];
        
        foreach ($keywords as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/', $line)) {
                $modifiers[] = $keyword;
            }
        }
        
        // Sort modifiers by their position in the line to maintain lexical order
        usort($modifiers, function (string $a, string $b) use ($line): int {
            $posA = strpos($line, $a);
            $posB = strpos($line, $b);
            return $posA <=> $posB;
        });

        return $modifiers;
    }
}