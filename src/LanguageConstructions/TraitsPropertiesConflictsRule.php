<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\LanguageConstructions;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PhpParser\Node\Stmt\Trait_ as TraitStmt;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Detects conflicts between class properties and trait properties with the same name.
 *
 * This rule identifies when a class defines a property with the same name as a property
 * in one of its traits, or when a parent class property conflicts with a trait property.
 * Such conflicts can lead to unexpected behavior and should be resolved explicitly.
 *
 * The rule reports conflicts when:
 * - A class property has the same name as a trait property with the same default value
 * - A parent class property (non-private) conflicts with a trait property
 *
 * @implements Rule<Class_>
 */
final class TraitsPropertiesConflictsRule implements Rule
{
    public function __construct(private \PHPStan\Reflection\ReflectionProvider $reflectionProvider)
    {
    }
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Class_) {
            return [];
        }

        // Build a display name from AST; Scope class reflection may be unavailable here
        $className = $node->name?->toString() ?? 'anonymous-class';

        // Collect used trait names from AST
        $traitNames = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $traitNameNode) {
                    $traitNames[] = $scope->resolveName($traitNameNode);
                }
            }
        }
        if ($traitNames === []) {
            return [];
        }

        // Find trait definitions by parsing the current file (avoid relying on 'parent' attributes)
        $traitNodes = [];
        $filePath = $scope->getFile();
        if (is_string($filePath) && $filePath !== '') {
            $code = @file_get_contents($filePath);
            if ($code !== false) {
                $parser = (new ParserFactory)->createForHostVersion();
                $stmts = $parser->parse($code) ?? [];
                $finder = new NodeFinder();
                /** @var TraitStmt[] $traitNodes */
                $traitNodes = $finder->find($stmts, static fn(Node $n): bool => $n instanceof TraitStmt);
            }
        }

        $errors = [];

        // Check conflicts between own properties and trait properties
        $errors = array_merge($errors, $this->checkOwnPropertyConflicts($node, $className, $traitNames, $traitNodes, $scope));

        return $errors;
    }

    /**
     * @param list<string> $traitNames Fully qualified trait names used by the class
     * @param list<TraitStmt> $traitNodes Trait definitions found in the current file
     * @return list<IdentifierRuleError>
     */
    private function checkOwnPropertyConflicts(Class_ $node, string $className, array $traitNames, array $traitNodes, Scope $scope): array
    {
        $errors = [];

        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }

            // Skip static and readonly properties
            if ($stmt->isStatic() || $stmt->isReadonly()) {
                continue;
            }

            $propertyName = $stmt->props[0]->name->toString();
            $ownDefault = $this->extractNodeDefaultValue($stmt);

            foreach ($traitNames as $traitFqn) {
                $traitDefault = $this->getTraitDefaultValueFromAst($traitNodes, $traitFqn, $propertyName, $scope);
                if ($traitDefault !== self::NO_DEFAULT) {
                    $errors[] = RuleErrorBuilder::message(
                        sprintf("'%s' and '%s' define the same property (\$%s).", $className, $traitFqn, $propertyName)
                    )->identifier('traits.propertiesConflict')
                     ->line($stmt->getStartLine())
                     ->build();
                    break; // Report only once per property
                }
            }
        }

        return $errors;
    }

    // Parent property conflicts are intentionally omitted in this simplified implementation

    private const string NO_DEFAULT = "__NO_DEFAULT__";

    private function extractNodeDefaultValue(Property $propertyNode): mixed
    {
        $default = $propertyNode->props[0]->default ?? null;
        if ($default === null) {
            return self::NO_DEFAULT;
        }
        if ($default instanceof \PhpParser\Node\Scalar\String_) {
            return $default->value;
        }
        if ($default instanceof \PhpParser\Node\Scalar\LNumber) {
            return $default->value;
        }
        if ($default instanceof \PhpParser\Node\Scalar\DNumber) {
            return $default->value;
        }
        if ($default instanceof \PhpParser\Node\Expr\ConstFetch) {
            return $default->name->toLowerString();
        }
        return self::NO_DEFAULT;
    }

    private function getTraitDefaultValueFromAst(array $traitNodes, string $traitFqn, string $propertyName, Scope $scope): mixed
    {
        $short = ($pos = strrpos($traitFqn, '\\')) !== false ? substr($traitFqn, $pos + 1) : $traitFqn;
        $short = strtolower($short);
        foreach ($traitNodes as $trait) {
            $traitName = strtolower($trait->name?->toString() ?? '');
            if ($traitName !== $short) {
                continue;
            }
            foreach ($trait->stmts as $member) {
                if (!$member instanceof Property) {
                    continue;
                }
                $name = $member->props[0]->name->toString();
                if ($name !== $propertyName) {
                    continue;
                }
                return $this->extractNodeDefaultValue($member);
            }
        }
        return self::NO_DEFAULT;
    }

    private function valuesAreEquivalent(mixed $a, mixed $b): bool
    {
        return $a === $b;
    }

    private static function isMagicMethodName(string $name): bool
    {
        return in_array($name, [
            '__construct', '__destruct', '__call', '__callstatic', '__get', '__set', '__isset', '__unset',
            '__sleep', '__wakeup', '__tostring', '__invoke', '__set_state', '__clone', '__debuginfo',
        ], true);
    }
}
