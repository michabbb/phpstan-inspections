<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\DateTime;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates DateInterval constructor specifications for correctness.
 *
 * This rule checks if DateInterval objects are created with valid interval specifications,
 * ensuring that the interval strings follow the proper ISO 8601 duration format or
 * datetime-like format to prevent runtime errors.
 *
 * @implements Rule<New_>
 */
final class DateIntervalSpecificationRule implements Rule
{
    private const string REGULAR_PATTERN = '/^P((\d+Y)?(\d+M)?(\d+D)?(\d+W)?)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/';
    private const string DATETIME_LIKE_PATTERN = '/^P\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/';

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        // Check if we're instantiating DateInterval
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $node->class->toString();
        if ($className !== 'DateInterval') {
            return [];
        }

        // Check if exactly one parameter is provided
        if (count($node->args) !== 1) {
            return [];
        }

        $argument = $node->args[0]->value;

        // Only analyze string literals
        if (!$argument instanceof String_) {
            return [];
        }

        $intervalSpec = $argument->value;

        // Check if the specification matches either valid pattern
        if (!preg_match(self::REGULAR_PATTERN, $intervalSpec) && 
            !preg_match(self::DATETIME_LIKE_PATTERN, $intervalSpec)) {
            return [
                RuleErrorBuilder::message('Date interval specification seems to be invalid.')
                    ->identifier('dateInterval.invalidSpecification')
                    ->line($node->getStartLine())
                    ->build(),
            ];
        }

        return [];
    }
}