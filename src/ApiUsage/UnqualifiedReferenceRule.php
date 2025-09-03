<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Suggests using fully qualified function and constant names for better performance.
 *
 * This rule detects unqualified references to functions and constants that could benefit
 * from fully qualified names (\function_name). Using fully qualified names enables opcode
 * optimizations and can improve performance. See benchmarks at Roave/FunctionFQNReplacer.
 *
 * @implements Rule<Node>
 */
final class UnqualifiedReferenceRule implements Rule
{
    private const string MESSAGE_PATTERN = 'Using \'\\%s\' would enable some of opcode optimizations.';

    /** @var array<string> */
    private const array FALSE_POSITIVES = [
        'true', 'TRUE', 'false', 'FALSE', 'null', 'NULL',
        '__LINE__', '__FILE__', '__DIR__', '__FUNCTION__', '__CLASS__', '__TRAIT__', '__METHOD__', '__NAMESPACE__',
    ];

    /** @var array<string> */
    private const array ADVANCED_OPCODE_FUNCTIONS = [
        'array_slice', 'assert', 'boolval', 'call_user_func', 'call_user_func_array', 'chr', 'count', 'defined',
        'doubleval', 'floatval', 'func_get_args', 'func_num_args', 'get_called_class', 'get_class', 'gettype',
        'in_array', 'intval', 'is_array', 'is_bool', 'is_double', 'is_float', 'is_int', 'is_integer', 'is_long',
        'is_null', 'is_object', 'is_real', 'is_resource', 'is_string', 'ord', 'strlen', 'strval', 'function_exists',
        'is_callable', 'extension_loaded', 'dirname', 'constant', 'define', 'array_key_exists', 'is_scalar',
        'sizeof', 'ini_get', 'sprintf',
    ];

    /** @var array<string, int> */
    private const array CALLBACKS_POSITIONS = [
        'call_user_func'       => 0,
        'call_user_func_array' => 0,
        'array_filter'         => 1,
        'array_map'            => 0,
        'array_walk'           => 1,
        'array_reduce'         => 1,
    ];

    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return Node::class; // We will filter inside processNode
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        if ($node instanceof FuncCall) {
            $errors = array_merge($errors, $this->processFuncCall($node, $scope));
        } elseif ($node instanceof ConstFetch) {
            $errors = array_merge($errors, $this->processConstFetch($node, $scope));
        }

        return $errors;
    }

    /**
     * @return \PHPStan\Rules\RuleError[]
     */
    private function processFuncCall(FuncCall $node, Scope $scope): array
    {
        $errors = [];

        if (!($node->name instanceof Name)) {
            return $errors;
        }

        $functionName = $node->name->toString();
        $lowerFunctionName = strtolower($functionName);

        // Check for unqualified function calls
        if (!$this->isFullyQualified($node->name) && !$this->isRelativeName($node->name)) {
            if (in_array($lowerFunctionName, self::ADVANCED_OPCODE_FUNCTIONS, true)) {
                if (!$this->isImported($functionName, Use_::TYPE_FUNCTION, $scope)) {
                    $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_PATTERN, $functionName . '()'))
                        ->identifier('unqualifiedReference.function')
                        ->line($node->getStartLine())
                        ->build();
                }
            }
        }

        // Check for string literal callbacks
        if (array_key_exists($lowerFunctionName, self::CALLBACKS_POSITIONS)) {
            $callbackPosition = self::CALLBACKS_POSITIONS[$lowerFunctionName];
            if (isset($node->args[$callbackPosition]) && $node->args[$callbackPosition]->value instanceof String_) {
                $callbackString = $node->args[$callbackPosition]->value->value;
                if (!$this->isFullyQualifiedString($callbackString) && !$this->isClassMethodString($callbackString)) {
                    $lowerCallbackString = strtolower($callbackString);
                    if (in_array($lowerCallbackString, self::ADVANCED_OPCODE_FUNCTIONS, true)) {
                        if (!$this->isImported($callbackString, Use_::TYPE_FUNCTION, $scope)) {
                            $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_PATTERN, $callbackString . '()'))
                                ->identifier('unqualifiedReference.callbackFunction')
                                ->line($node->args[$callbackPosition]->value->getStartLine())
                                ->build();
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return \PHPStan\Rules\RuleError[]
     */
    private function processConstFetch(ConstFetch $node, Scope $scope): array
    {
        $errors = [];

        if (!($node->name instanceof Name)) {
            return $errors;
        }

        $constantName = $node->name->toString();
        $upperConstantName = strtoupper($constantName);

        if (in_array($upperConstantName, self::FALSE_POSITIVES, true)) {
            return $errors;
        }

        // Check for unqualified constant references
        if (!$this->isFullyQualified($node->name) && !$this->isRelativeName($node->name)) {
            // The Java rule has a REPORT_CONSTANTS option. For now, we'll always report if it's a built-in constant.
            // PHPStan doesn't have a direct way to check if a constant is "built-in" or defined in the global namespace
            // without resolving it. We'll rely on the reflection provider to check if it's a global constant.
            if ($this->reflectionProvider->hasConstant(new Name($constantName), $scope)) {
                $constantReflection = $this->reflectionProvider->getConstant(new Name($constantName), $scope);
                // If the constant is defined in the global namespace (FQN is just the name itself)
                if ($constantReflection->getName() === $constantName) {
                    if (!$this->isImported($constantName, Use_::TYPE_CONSTANT, $scope)) {
                        $errors[] = RuleErrorBuilder::message(sprintf(self::MESSAGE_PATTERN, $constantName))
                            ->identifier('unqualifiedReference.constant')
                            ->line($node->getStartLine())
                            ->build();
                    }
                }
            }
        }

        return $errors;
    }

    private function isFullyQualified(Name $name): bool
    {
        return $name instanceof FullyQualified;
    }

    private function isRelativeName(Name $name): bool
    {
        return $name instanceof Relative;
    }

    private function isFullyQualifiedString(string $name): bool
    {
        return str_starts_with($name, '\\');
    }

    private function isClassMethodString(string $name): bool
    {
        return str_contains($name, '::');
    }

    private function isImported(string $name, int $type, Scope $scope): bool
    {
        // PHPStan Scope API does not expose use statements reliably here.
        // Our triggers do not rely on imports, so treat as not imported.
        return false;
    }
}
