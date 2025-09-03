<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Debug;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects forgotten debug statements that can disclose sensitive information,
 * impact performance, or break applications in production.
 *
 * This rule identifies calls to debug functions and methods that are likely
 * forgotten debug statements. It allows debug calls in the following contexts:
 * - Inside debug functions themselves (recursive debug calls)
 * - When preceded by ob_start() (buffered output)
 * - When certain functions have the required number of arguments
 *
 * @implements Rule<Node>
 */
class ForgottenDebugOutputRule implements Rule
{
    private const string MESSAGE = 'Please ensure this is not a forgotten debug statement.';

    /**
     * @var array<string, int> Functions that require a specific number of arguments to be considered valid
     */
    private const array FUNCTIONS_REQUIREMENTS = [
        'debug_print_backtrace' => -1, // Always considered debug
        'debug_zval_dump' => -1,       // Always considered debug
        'phpinfo' => 1,                // Valid if has 1+ arguments
        'print_r' => 2,                // Valid if has 2+ arguments
        'var_export' => 2,             // Valid if has 2+ arguments
        'var_dump' => -1,              // Always considered debug
    ];

    /**
     * @var array<string> List of debug functions
     */
    private const array DEBUG_FUNCTIONS = [
        'debug_print_backtrace',
        'debug_zval_dump',
        'error_log',
        'phpinfo',
        'print_r',
        'var_export',
        'var_dump',
        // Codeception
        '\\Codeception\\Util\\Debug::pause',
        '\\Codeception\\Util\\Debug::debug',
        // Doctrine
        '\\Doctrine\\Common\\Util\\Debug::dump',
        '\\Doctrine\\Common\\Util\\Debug::export',
        // Symfony
        '\\Symfony\\Component\\Debug\\Debug::enable',
        '\\Symfony\\Component\\Debug\\ErrorHandler::register',
        '\\Symfony\\Component\\Debug\\ExceptionHandler::register',
        '\\Symfony\\Component\\Debug\\DebugClassLoader::enable',
        // Zend
        '\\Zend\\Debug\\Debug::dump',
        '\\Zend\\Di\\Display\\Console::export',
        // Typo3
        '\\TYPO3\\CMS\\Core\\Utility\\DebugUtility::debug',
        // Laravel
        '\\Illuminate\\Support\\Debug\\Dumper::dump',
        'dd',
        'dump',
        // Buggregator
        'trap',
        // Drupal
        'dpm',
        'dsm',
        'dvm',
        'kpr',
        'dpq',
        // XDebug
        'xdebug_break',
        'xdebug_call_class',
        'xdebug_call_file',
        'xdebug_call_function',
        'xdebug_call_line',
        'xdebug_code_coverage_started',
        'xdebug_debug_zval',
        'xdebug_debug_zval_stdout',
        'xdebug_dump_superglobals',
        'xdebug_enable',
        'xdebug_get_code_coverage',
        'xdebug_get_collected_errors',
        'xdebug_get_declared_vars',
        'xdebug_get_function_stack',
        'xdebug_get_headers',
        'xdebug_get_monitored_functions',
        'xdebug_get_profiler_filename',
        'xdebug_get_stack_depth',
        'xdebug_get_tracefile_name',
        'xdebug_is_enabled',
        'xdebug_memory_usage',
        'xdebug_peak_memory_usage',
        'xdebug_print_function_stack',
        'xdebug_start_code_coverage',
        'xdebug_start_error_collection',
        'xdebug_start_function_monitor',
        'xdebug_start_trace',
        'xdebug_stop_code_coverage',
        'xdebug_stop_error_collection',
        'xdebug_stop_function_monitor',
        'xdebug_stop_trace',
        'xdebug_time_index',
        'xdebug_var_dump',
        // Wordpress
        'wp_die',
    ];


    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof FuncCall) {
            return $this->processFunctionCall($node, $scope);
        }

        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            return $this->processMethodCall($node, $scope);
        }

        return [];
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function processFunctionCall(FuncCall $node, Scope $scope): array
    {
        $functionName = $this->getFunctionName($node, $scope);
        if ($functionName === null) {
            return [];
        }

        if (!in_array($functionName, self::DEBUG_FUNCTIONS, true)) {
            return [];
        }

        // Check if this is a use statement (not a call)
        if ($node->getAttribute('parent') instanceof Node\Stmt\UseUse) {
            return [];
        }

        // Check if we're inside a debug function
        if ($this->isInDebugFunction($node, $scope)) {
            return [];
        }

        // Check if output is buffered
        if ($this->isBuffered($node, $scope)) {
            return [];
        }

        // Check argument requirements
        $paramsNeeded = self::FUNCTIONS_REQUIREMENTS[$functionName] ?? null;
        if ($paramsNeeded !== null && $paramsNeeded !== -1) {
            $argCount = count($node->getArgs());
            if ($argCount >= $paramsNeeded) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('debug.forgottenOutput')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function processMethodCall(Node $node, Scope $scope): array
    {
        $methodName = $this->getMethodName($node);
        if ($methodName === null) {
            return [];
        }

        $className = $this->getClassName($node, $scope);
        if ($className === null) {
            return [];
        }

        $fullMethodName = $className . '::' . $methodName;

        // Skip legitimate logger calls
        if ($this->isLegitimateLoggerCall($className, $methodName)) {
            return [];
        }

        if (!in_array($fullMethodName, self::DEBUG_FUNCTIONS, true)) {
            return [];
        }

        // Check if we're inside a debug function
        if ($this->isInDebugFunction($node, $scope)) {
            return [];
        }

        // Check if output is buffered
        if ($this->isBuffered($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('debug.forgottenOutput')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function getFunctionName(FuncCall $node, Scope $scope): ?string
    {
        if ($node->name instanceof Node\Name) {
            return $scope->resolveName($node->name);
        }

        return null;
    }

    private function getMethodName(Node $node): ?string
    {
        if ($node instanceof MethodCall && $node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }

        if ($node instanceof StaticCall && $node->name instanceof Node\Identifier) {
            return $node->name->toString();
        }

        return null;
    }

    private function getClassName(Node $node, Scope $scope): ?string
    {
        if ($node instanceof MethodCall) {
            $varType = $scope->getType($node->var);
            $classNames = $varType->getObjectClassNames();
            return $classNames[0] ?? null;
        }

        if ($node instanceof StaticCall && $node->class instanceof Node\Name) {
            return $scope->resolveName($node->class);
        }

        return null;
    }

    private function isInDebugFunction(Node $node, Scope $scope): bool
    {
        $function = $scope->getFunction();
        if ($function === null) {
            return false;
        }

        $functionName = $function->getName();
        $className = $scope->getClassReflection()?->getName();

        // Check if current function is explicitly in debug functions list
        if (in_array($functionName, self::DEBUG_FUNCTIONS, true)) {
            return true;
        }

        if ($className !== null) {
            $fullFunctionName = $className . '::' . $functionName;
            if (in_array($fullFunctionName, self::DEBUG_FUNCTIONS, true)) {
                return true;
            }
        }

        // Dynamic detection: Check if function name suggests debugging purpose
        if (str_contains(strtolower($functionName), 'debug')) {
            return true;
        }

        // Check if function name suggests testing/development purpose
        $developmentPatterns = ['test', 'mock', 'stub', 'fake', 'dummy'];
        $lowerFunctionName = strtolower($functionName);
        foreach ($developmentPatterns as $pattern) {
            if (str_contains($lowerFunctionName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isBuffered(Node $node, Scope $scope): bool
    {
        // Find the parent statement
        $parent = $node->getAttribute('parent');
        while ($parent !== null && !($parent instanceof Node\Stmt)) {
            $parent = $parent->getAttribute('parent');
        }

        if (!$parent instanceof Node\Stmt) {
            return false;
        }

        // Look for preceding ob_start() call
        $current = $parent;
        while ($current !== null) {
            if ($current instanceof Expression &&
                $current->expr instanceof FuncCall &&
                $current->expr->name instanceof Node\Name) {
                $funcName = $scope->resolveName($current->expr->name);
                if ($funcName === 'ob_start') {
                    return true;
                }
            }

            // Get previous sibling
            $prev = $current->getAttribute('previous');
            if ($prev === null) {
                break;
            }
            $current = $prev;
        }

        return false;
    }

    /**
     * Check if this is a legitimate logger call that should not be flagged as debug output
     * Uses dynamic class detection instead of hardcoded lists
     */
    private function isLegitimateLoggerCall(string $className, string $methodName): bool
    {
        $loggerMethods = [
            'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'
        ];

        // If method is not a logger method, it's not a logger call
        if (!in_array($methodName, $loggerMethods, true)) {
            return false;
        }

        // Check if class implements PSR-3 LoggerInterface pattern
        if (str_contains($className, 'LoggerInterface') || str_contains($className, 'Psr\\Log')) {
            return true;
        }

        // Check for common logger naming patterns
        $classBaseName = basename(str_replace('\\', '/', $className));
        
        // Logger classes typically end with 'Logger' or 'Log'
        if (str_ends_with($classBaseName, 'Logger') || str_ends_with($classBaseName, 'Log')) {
            return true;
        }

        // Check if namespace suggests logging functionality
        if (str_contains($className, '\\Log\\') || str_contains($className, '\\Logging\\')) {
            return true;
        }

        return false;
    }
}
