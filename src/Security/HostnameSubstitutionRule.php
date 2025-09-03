<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\Security;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects potential security vulnerabilities related to hostname substitution in PHP applications.
 *
 * This rule identifies:
 * - Usage of $_SERVER['SERVER_NAME'] or $_SERVER['HTTP_HOST'] in concatenation expressions forming email addresses
 * - Assignment of these server variables to variables or properties whose names match domain/email/host patterns
 *
 * @implements Rule<Node>
 */
class HostnameSubstitutionRule implements Rule
{
    private const string MESSAGE_EMAIL_GENERATION = "The email generation can be compromised via '\$_SERVER['%s']', consider introducing whitelists.";
    private const string MESSAGE_DOMAIN_COMPROMISE = "The domain here can be compromised, consider introducing whitelists.";

    private const array SERVER_ATTRIBUTES = ['SERVER_NAME', 'HTTP_HOST'];
    private const string TARGET_NAME_PATTERN = '/.*(?:domain|email|host).*/i';

    public function getNodeType(): string
    {
        return Node::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // Check concatenation patterns that form email addresses
        if ($node instanceof Concat) {
            $errors = array_merge($errors, $this->checkEmailConcatenation($node));
        }

        // Check assignments to suspicious variable names
        if ($node instanceof Assign) {
            $errors = array_merge($errors, $this->checkSuspiciousAssignment($node));
        }

        return $errors;
    }

    private function checkEmailConcatenation(Concat $concat): array
    {
        // Check if left side ends with "@" and right side is $_SERVER['SERVER_NAME'] or $_SERVER['HTTP_HOST']
        $left = $concat->left;
        $right = $concat->right;

        // Handle nested concatenation (e.g., "user@" . $_SERVER['SERVER_NAME'])
        if ($left instanceof Concat) {
            $left = $left->right;
        }

        // Check if we have a string ending with "@" concatenated with server variable
        if ($left instanceof String_ && str_ends_with($left->value, '@')) {
            $serverAttribute = $this->getServerVariableAttribute($right);
            if ($serverAttribute !== null) {
                return [
                    RuleErrorBuilder::message(sprintf(self::MESSAGE_EMAIL_GENERATION, $serverAttribute))
                        ->identifier('security.hostnameSubstitution.emailGeneration')
                        ->line($concat->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function checkSuspiciousAssignment(Assign $assign): array
    {
        $serverAttribute = $this->getServerVariableAttribute($assign->expr);
        if ($serverAttribute === null) {
            return [];
        }

        // Check if we're assigning to a variable with suspicious name
        if ($assign->var instanceof Variable && is_string($assign->var->name)) {
            $variableName = $assign->var->name;
            if ($this->matchesTargetPattern($variableName)) {
                return [
                    RuleErrorBuilder::message(self::MESSAGE_DOMAIN_COMPROMISE)
                        ->identifier('security.hostnameSubstitution.domainCompromise')
                        ->line($assign->getStartLine())
                        ->build(),
                ];
            }
        }

        // Check if we're assigning to a property with suspicious name
        if ($assign->var instanceof Node\Expr\PropertyFetch) {
            $propertyName = $this->getPropertyName($assign->var);
            if ($propertyName !== null && $this->matchesTargetPattern($propertyName)) {
                return [
                    RuleErrorBuilder::message(self::MESSAGE_DOMAIN_COMPROMISE)
                        ->identifier('security.hostnameSubstitution.domainCompromise')
                        ->line($assign->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }

    private function getServerVariableAttribute(Node $node): ?string
    {
        if (!$node instanceof ArrayDimFetch) {
            return null;
        }

        $var = $node->var;
        if (!$var instanceof Variable || $var->name !== '_SERVER') {
            return null;
        }

        $dim = $node->dim;
        if (!$dim instanceof String_) {
            return null;
        }

        $attribute = $dim->value;
        return in_array($attribute, self::SERVER_ATTRIBUTES, true) ? $attribute : null;
    }

    private function getPropertyName(Node\Expr\PropertyFetch $propertyFetch): ?string
    {
        if ($propertyFetch->name instanceof Node\Identifier) {
            return $propertyFetch->name->toString();
        }

        return null;
    }

    private function matchesTargetPattern(string $name): bool
    {
        return preg_match(self::TARGET_NAME_PATTERN, $name) === 1;
    }
}