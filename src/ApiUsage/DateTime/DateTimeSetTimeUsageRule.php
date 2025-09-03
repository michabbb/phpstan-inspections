<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\DateTime;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Detects incorrect usage of DateTime::setTime() with microseconds parameter.
 *
 * This rule identifies calls to DateTime::setTime() with 4 arguments (including microseconds)
 * and reports that the call will return false if the PHP version is below 7.1.
 *
 * The rule detects:
 * - DateTime::setTime($hour, $minute, $second, $microseconds) - should be avoided in PHP < 7.1
 *
 * @implements Rule<MethodCall>
 */
class DateTimeSetTimeUsageRule implements Rule
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

        // Check if method name is setTime
        if (!$node->name instanceof Node\Identifier || $node->name->toString() !== 'setTime') {
            return [];
        }

        // Check if called on DateTime or subclass
        $calledOnType = $scope->getType($node->var);
        if (!$this->isDateTimeType($calledOnType)) {
            return [];
        }

        // Check if exactly 4 arguments (hour, minute, second, microseconds)
        if (count($node->getArgs()) !== 4) {
            return [];
        }

        // Report on the microseconds parameter (4th argument)
        $microsecondsArg = $node->getArgs()[3];

        return [
            RuleErrorBuilder::message("The call will return false ('microseconds' parameter is available in PHP 7.1+).")
                ->identifier('datetime.setTimeUsage')
                ->line($microsecondsArg->getStartLine())
                ->build(),
        ];
    }

    private function isDateTimeType(\PHPStan\Type\Type $type): bool
    {
        if ($type instanceof ObjectType) {
            $className = $type->getClassName();
            return $className === 'DateTime' || is_subclass_of($className, 'DateTime');
        }

        return false;
    }
}