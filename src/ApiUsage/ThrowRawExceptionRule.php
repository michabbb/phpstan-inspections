<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects problematic exception throwing patterns.
 *
 * This rule identifies two issues with exception handling:
 * 1. Throwing raw \Exception instead of more specific SPL exceptions
 * 2. Throwing exceptions without a descriptive message
 *
 * Suggests using more specific exception types and providing meaningful error messages.
 *
 * @implements Rule<Throw_>
 */
final class ThrowRawExceptionRule implements Rule
{
    public const string MESSAGE_RAW_EXCEPTION = '\Exception is too general. Consider throwing one of SPL exceptions instead.';
    public const string MESSAGE_NO_ARGUMENTS = 'This exception is thrown without a message. Consider adding one to help clarify or troubleshoot the exception.';

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getNodeType(): string
    {
        return Throw_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->expr instanceof New_) {
            return [];
        }

        $newExpression = $node->expr;
        $class = $newExpression->class;

        if (!$class instanceof Name) {
            return [];
        }

        $className = (string) $class;
        $errors = [];

        // Condition 1: Throwing raw \Exception
        if ($className === 'Exception' || $className === '\Exception') {
            $errors[] = RuleErrorBuilder::message(self::MESSAGE_RAW_EXCEPTION)
                ->identifier('exception.raw')
                ->line($node->getStartLine())
                ->build();
        }

        // Condition 2: Exception thrown without a message (and matches isTarget heuristic)
        // Replicate REPORT_MISSING_ARGUMENTS = true from Java code
        if (count($newExpression->args) === 0) {
            $fullyQualifiedClassName = $scope->resolveName($class);
            if ($this->reflectionProvider->hasClass($fullyQualifiedClassName)) {
                $classReflection = $this->reflectionProvider->getClass($fullyQualifiedClassName);

                $constructor = $classReflection->getConstructor();
                // Standard exceptions like RuntimeException have 3 optional parameters.
                // If a custom exception has 3 parameters and is thrown without arguments,
                // it's likely a candidate for needing a message.
                if ($constructor !== null) {
                    $variants = $constructor->getVariants();
                    if (!empty($variants)) {
                        $parametersAcceptor = $variants[0];
                        if (count($parametersAcceptor->getParameters()) === 3) {
                            $errors[] = RuleErrorBuilder::message(self::MESSAGE_NO_ARGUMENTS)
                                ->identifier('exception.missingMessage')
                                ->line($node->getStartLine())
                                ->build();
                        }
                    }
                }
            }
        }

        return $errors;
    }
}