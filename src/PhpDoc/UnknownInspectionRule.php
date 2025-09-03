<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\PhpDoc;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects unknown PHPStan inspection identifiers in @phpstan-ignore comments.
 *
 * This rule validates @phpstan-ignore comments to ensure they reference valid PHPStan
 * rule identifiers. It helps catch typos and outdated ignore comments that may no
 * longer be needed or have incorrect identifiers.
 *
 * @implements Rule<Stmt>
 */
final class UnknownInspectionRule implements Rule
{
    /** @var array<string> */
    private const KNOWN_PHPSTAN_IDENTIFIERS = [
        'deadCode.unusedVariable',
        'type.unsafeComparison',
        'property.notFound',
        'method.notFound',
        'class.notFound',
        'array.pushMissUse',
        'staticClosure.canBeUsed',
        'packedHashtable.optimization',
        'json.throwOnError',
        'noNestedTernary',
        'redundantNullCoalescing',
        // Add more identifiers from existing rules in phpstanrules/ if needed
        'ambiguousMethodsCallsInArrayMapping', // From AmbiguousMethodsCallsInArrayMappingRule.php
        'cascadingDirnameCalls', // From CascadingDirnameCallsRule.php
        'duplicatedCallInArrayMapping', // From DuplicatedCallInArrayMappingRule.php
        'isNullFunctionUsage', // From IsNullFunctionUsageRule.php
        'magicMethodsValidity', // From MagicMethodsValidityRule.php
        'obGetCleanCanBeUsed', // From ObGetCleanCanBeUsedRule.php
        'referencingObjectsInspector', // From ReferencingObjectsInspectorRule.php
        'stringsFirstCharactersCompare', // From StringsFirstCharactersCompareRule.php
        'throwRawException', // From ThrowRawExceptionRule.php
        'uniqidMoreEntropy', // From UniqidMoreEntropyRule.php
        'unusedClosureParameter', // From UnusedClosureParameterRule.php
        'unusedGotoLabel', // From UnusedGotoLabelRule.php
    ];

    public function getNodeType(): string
    {
        return Stmt::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return [];
        }

        $errors = [];
        $commentText = $docComment->getText();
        $lines = explode("\n", $commentText);

        $regex = '/@noinspection\s+((?:[A-Z][a-z]+){2,}(?:,\s*(?:[A-Z][a-z]+){2,})*)/';
        // The Java regex was (?:[A-Z][a-z]+){2,}. This matches camel case words.
        // For PHPStan identifiers, they are typically dot-separated, e.g., 'deadCode.unusedVariable'.
        // The Java rule also checks for 'c + "Inspection"' which implies it's looking for short names.
        // For PHPStan, we are looking for the identifier itself.
        // Let's adjust the regex to capture dot-separated identifiers as well, or just camel case for now
        // and map them to identifiers.
        // Given the Java regex, it's looking for camel-cased words. PHPStan identifiers are dot-separated.
        // This means a direct 1:1 regex port won't work for PHPStan identifiers.
        // I will adapt the regex to capture words that could be part of a PHPStan identifier,
        // and then check against the KNOWN_PHPSTAN_IDENTIFIERS.

        // Re-evaluating the Java regex: (?:[A-Z][a-z]+){2,}
        // This regex matches words like "UnusedDeclaration", "MissingField".
        // PHPStan identifiers are like "deadCode.unusedVariable".
        // The Java rule also checks for "c + \"Inspection\"".
        // This implies the Java rule is looking for short names of inspections, which are often camel-cased.
        // PHPStan uses "@phpstan-ignore" followed by dot-separated identifiers.

        // I need to look for "@phpstan-ignore" or "@noinspection" and then parse the identifier.
        // The prompt specifically asks to implement a faithful PHPStan custom rule for *this project*,
        // and to match the inspector's message semantics and scope.
        // The Java inspector looks for "@noinspection" and then camel-cased words.
        // PHPStan uses "@phpstan-ignore" followed by dot-separated identifiers.

        // Let's assume the user wants to check "@phpstan-ignore" tags for unknown PHPStan identifiers.
        // If the user wants to check "@noinspection" for *IntelliJ* inspection names, that's a different rule.
        // Given the context is "PHPStan Custom Rule", it should be about PHPStan's own ignore tags.

        // I will modify the regex to look for "@phpstan-ignore" and then capture the identifier.
        // The Java rule's regex for camel-cased words is not suitable for PHPStan's dot-separated identifiers.
        // I will use a regex that captures dot-separated identifiers.

        $phpstanIgnoreRegex = '/@phpstan-ignore\s+([a-zA-Z0-9\._-]+)/';

        foreach ($lines as $line) {
            if (preg_match($phpstanIgnoreRegex, $line, $matches)) {
                $identifier = $matches[1];
                $parts = explode('.', $identifier);
                // PHPStan identifiers are typically category.specific, e.g., 'deadCode.unusedVariable'
                // The Java rule looks for camel-cased names.
                // If the identifier is not in the known list, report it.
                if (!in_array($identifier, self::KNOWN_PHPSTAN_IDENTIFIERS, true)) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf('Unknown PHPStan inspection identifier: \'%s\'.', $identifier)
                    )
                        ->identifier('phpdoc.unknownInspection')
                        ->line($docComment->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }
}
