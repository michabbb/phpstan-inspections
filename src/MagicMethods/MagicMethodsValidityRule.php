<?php
declare(strict_types=1);

namespace macropage\PHPStan\Inspections\MagicMethods;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Magic methods validity rule mirroring EA Extended MagicMethodsValidityInspector.
 *
 * Scope: validates signatures and basic constraints of known magic methods;
 * additionally reports non-magic methods starting with '__'.
 */
final class MagicMethodsValidityRule implements Rule
{
    /** @var array<string, true> */
    private const KNOWN_NON_MAGIC = [
        // Magento & co
        '__inject' => true,
        '__prepare' => true,
        '__toArray' => true,
        '__' => true,
        // SoapClient
        '__doRequest' => true,
        '__getCookies' => true,
        '__getFunctions' => true,
        '__getLastRequest' => true,
        '__getLastRequestHeaders' => true,
        '__getLastResponse' => true,
        '__getLastResponseHeaders' => true,
        '__getTypes' => true,
        '__setCookie' => true,
        '__setLocation' => true,
        '__setSoapHeaders' => true,
        '__soapCall' => true,
    ];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<RuleError>
     */
    public function processNode(Node $node, \PHPStan\Analyser\Scope $scope): array
    {
        if ($node->isAbstract()) {
            return [];
        }

        $methodName = $node->name instanceof Identifier ? $node->name->toString() : '';
        if ($methodName === '' || $methodName[0] !== '_') {
            return [];
        }

        $class = $scope->getClassReflection();
        if ($class === null) {
            return [];
        }

        $errors = [];

        switch ($methodName) {
            case '__construct':
                // cannot be static; cannot declare return type
                $this->cannotBeStatic($node, $errors);
                $this->cannotHaveReturnType($node, $errors);
                break;

            case '__destruct':
            case '__clone':
                $this->cannotBeStatic($node, $errors);
                $this->cannotHaveReturnType($node, $errors);
                $this->cannotTakeAnyArguments($node, $errors);
                break;

            case '__get':
            case '__isset':
            case '__unset':
                $this->takesExactArgs($node, 1, $errors);
                $this->cannotBeStatic($node, $errors);
                $this->mustBePublic($node, $errors);
                $this->noByRefParams($node, $errors);
                break;

            case '__set':
            case '__call':
                $this->takesExactArgs($node, 2, $errors);
                $this->cannotBeStatic($node, $errors);
                $this->mustBePublic($node, $errors);
                $this->noByRefParams($node, $errors);
                break;

            case '__callStatic':
                $this->takesExactArgs($node, 2, $errors);
                $this->mustBeStatic($node, $errors);
                $this->mustBePublic($node, $errors);
                $this->noByRefParams($node, $errors);
                break;

            case '__toString':
                $this->cannotBeStatic($node, $errors);
                $this->cannotTakeAnyArguments($node, $errors);
                $this->mustBePublic($node, $errors);
                $this->mustReturnTypeOf($node, ['string'], $errors);
                break;

            case '__debugInfo':
                $this->cannotBeStatic($node, $errors);
                $this->cannotTakeAnyArguments($node, $errors);
                $this->mustBePublic($node, $errors);
                $this->mustReturnTypeOf($node, ['array', 'null'], $errors);
                break;

            case '__set_state':
                $this->takesExactArgs($node, 1, $errors);
                $this->mustBeStatic($node, $errors);
                $this->mustBePublic($node, $errors);
                // must return self|static|ThisClass
                $expected = ['self', 'static', ltrim($class->getName(), '\\')];
                $this->mustReturnTypeOf($node, $expected, $errors);
                break;

            case '__invoke':
                $this->cannotBeStatic($node, $errors);
                $this->mustBePublic($node, $errors);
                break;

            case '__wakeup':
                $this->cannotBeStatic($node, $errors);
                $this->cannotTakeAnyArguments($node, $errors);
                $this->cannotReturnValue($node, $errors);
                break;

            case '__unserialize':
                $this->cannotBeStatic($node, $errors);
                $this->mustBePublic($node, $errors);
                $this->takesExactArgs($node, 1, $errors);
                $this->cannotReturnValue($node, $errors);
                break;

            case '__sleep':
            case '__serialize':
                $this->cannotBeStatic($node, $errors);
                $this->mustBePublic($node, $errors);
                $this->cannotTakeAnyArguments($node, $errors);
                $this->mustReturnTypeOf($node, ['array'], $errors);
                break;

            case '__autoload':
                $this->takesExactArgs($node, 1, $errors);
                $this->cannotReturnValue($node, $errors);
                $errors[] = RuleErrorBuilder::message("Has been deprecated in favour of 'spl_autoload_register(...)' as of PHP 7.2.0.")
                    ->identifier('magicMethods.autoloadDeprecated')
                    ->line($node->getStartLine())
                    ->build();
                break;

            default:
                if (str_starts_with($methodName, '__') && !isset(self::KNOWN_NON_MAGIC[$methodName])) {
                    $errors[] = RuleErrorBuilder::message("Only magic methods should start with '__'.")
                        ->identifier('magicMethods.notMagicPrefix')
                        ->line($node->getStartLine())
                        ->build();
                }
                // We intentionally do not implement MissingUnderscore/HasAlso/NoramllyCallsParent strategies here.
                break;
        }

        return $errors;
    }

    /** @param list<RuleError> $errors */
    private function cannotBeStatic(ClassMethod $node, array &$errors): void
    {
        if ($node->isStatic()) {
            $errors[] = RuleErrorBuilder::message('Magic method cannot be static.')
                ->identifier('magicMethods.cannotBeStatic')
                ->line($node->getStartLine())
                ->build();
        }
    }

    /** @param list<RuleError> $errors */
    private function mustBeStatic(ClassMethod $node, array &$errors): void
    {
        if (!$node->isStatic()) {
            $errors[] = RuleErrorBuilder::message('Magic method must be static.')
                ->identifier('magicMethods.mustBeStatic')
                ->line($node->getStartLine())
                ->build();
        }
    }

    /** @param list<RuleError> $errors */
    private function mustBePublic(ClassMethod $node, array &$errors): void
    {
        if (!$node->isPublic()) {
            $errors[] = RuleErrorBuilder::message('Magic method must be public.')
                ->identifier('magicMethods.mustBePublic')
                ->line($node->getStartLine())
                ->build();
        }
    }

    /** @param list<RuleError> $errors */
    private function cannotHaveReturnType(ClassMethod $node, array &$errors): void
    {
        if ($node->getReturnType() !== null) {
            $errors[] = RuleErrorBuilder::message('Magic method cannot declare a return type.')
                ->identifier('magicMethods.cannotHaveReturnType')
                ->line($node->getStartLine())
                ->build();
        }
    }

    /** @param list<RuleError> $errors */
    private function cannotReturnValue(ClassMethod $node, array &$errors): void
    {
        $nodeFinder = new NodeFinder();
        $returnStatements = $nodeFinder->findInstanceOf($node, Return_::class);

        foreach ($returnStatements as $returnStatement) {
            if ($returnStatement->expr !== null) {
                $errors[] = RuleErrorBuilder::message('Magic method cannot return a value.')
                    ->identifier('magicMethods.cannotReturnValue')
                    ->line($returnStatement->getStartLine())
                    ->build();
            }
        }
    }

    /** @param list<RuleError> $errors */
    private function cannotTakeAnyArguments(ClassMethod $node, array &$errors): void
    {
        if (\count($node->params) > 0) {
            $errors[] = RuleErrorBuilder::message('Magic method cannot take any arguments.')
                ->identifier('magicMethods.noArguments')
                ->line($node->getStartLine())
                ->build();
        }
    }

    /** @param list<RuleError> $errors */
    private function takesExactArgs(ClassMethod $node, int $expected, array &$errors): void
    {
        if (\count($node->params) !== $expected) {
            $errors[] = RuleErrorBuilder::message('Magic method must take exactly ' . $expected . ' argument' . ($expected === 1 ? '' : 's') . '.')
                ->identifier('magicMethods.exactArguments')
                ->line($node->getStartLine())
                ->build();
        }
    }

    /** @param list<RuleError> $errors */
    private function noByRefParams(ClassMethod $node, array &$errors): void
    {
        foreach ($node->params as $param) {
            if ($param->byRef) {
                $errors[] = RuleErrorBuilder::message('Magic method parameters cannot be passed by reference.')
                    ->identifier('magicMethods.noByRefParams')
                    ->line($node->getStartLine())
                    ->build();
                break;
            }
        }
    }

    /**
     * Validate return type if declared. Accepts union/nullable forms when all constituents are allowed.
     *
     * @param list<string> $allowedTypes
     * @param list<RuleError> $errors
     */
    private function mustReturnTypeOf(ClassMethod $node, array $allowedTypes, array &$errors): void
    {
        $type = $node->getReturnType();
        if ($type === null) {
            return; // absence of type is allowed; runtime will enforce
        }

        $all = $this->flattenTypeNames($type);
        foreach ($all as $name) {
            if (!\in_array($name, $allowedTypes, true)) {
                $errors[] = RuleErrorBuilder::message('Magic method must declare return type: ' . implode('|', $allowedTypes) . '.')
                    ->identifier('magicMethods.mustReturnType')
                    ->line($node->getStartLine())
                    ->build();
                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function flattenTypeNames(Node $type): array
    {
        if ($type instanceof UnionType) {
            $names = [];
            foreach ($type->types as $inner) {
                $names = array_merge($names, $this->flattenTypeNames($inner));
            }
            return $names;
        }
        if ($type instanceof NullableType) {
            return array_merge(['null'], $this->flattenTypeNames($type->type));
        }
        if ($type instanceof Identifier) {
            return [$type->toString()];
        }
        if ($type instanceof Name) {
            return [ltrim($type->toString(), '\\')];
        }
        return [];
    }
}

