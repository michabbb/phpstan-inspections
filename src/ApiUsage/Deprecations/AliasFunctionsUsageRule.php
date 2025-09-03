<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\ApiUsage\Deprecations;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of alias functions and suggests using the original functions instead.
 *
 * This rule identifies calls to PHP function aliases that should be replaced with their
 * canonical function names for better code maintainability and to avoid surprises from
 * backward-incompatible changes (e.g., mysqli_* aliases were dropped in PHP 5.4).
 *
 * The rule detects both regular aliases (which have direct replacements) and deprecated
 * aliases (which have been removed in certain PHP versions).
 *
 * @implements Rule<FuncCall>
 */
final class AliasFunctionsUsageRule implements Rule
{
    /**
     * @var array<string, string>
     */
    private const RELEVANT_ALIASES = [
        'close' => 'closedir',
        'is_double' => 'is_float',
        'is_integer' => 'is_int',
        'is_long' => 'is_int',
        'is_real' => 'is_float',
        'sizeof' => 'count',
        'doubleval' => 'floatval',
        'fputs' => 'fwrite',
        'join' => 'implode',
        'key_exists' => 'array_key_exists',
        'chop' => 'rtrim',
        'ini_alter' => 'ini_set',
        'is_writeable' => 'is_writable',
        'pos' => 'current',
        'show_source' => 'highlight_file',
        'strchr' => 'strstr',
        'set_file_buffer' => 'stream_set_write_buffer',
        'session_commit' => 'session_write_close',
        'socket_getopt' => 'socket_get_option',
        'socket_setopt' => 'socket_set_option',
        'openssl_get_privatekey' => 'openssl_pkey_get_private',
        'posix_errno' => 'posix_get_last_error',
        'ldap_close' => 'ldap_unbind',
        'pcntl_errno' => 'pcntl_get_last_error',
        'ftp_quit' => 'ftp_close',
        'socket_set_blocking' => 'stream_set_blocking',
        'stream_register_wrapper' => 'stream_wrapper_register',
        'socket_set_timeout' => 'stream_set_timeout',
        'socket_get_status' => 'stream_get_meta_data',
        'diskfreespace' => 'disk_free_space',
        'odbc_do' => 'odbc_exec',
        'odbc_field_precision' => 'odbc_field_len',
        'recode' => 'recode_string',
        'mysqli_escape_string' => 'mysqli_real_escape_string',
        'mysqli_execute' => 'mysqli_stmt_execute',
    ];

    /**
     * @var array<string, string>
     */
    private const DEPRECATED_ALIASES = [
        'mysqli_bind_param' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 5.4.0.',
        'mysqli_bind_result' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 5.4.0.',
        'mysqli_client_encoding' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 5.4.0.',
        'mysqli_fetch' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 5.4.0.',
        'mysqli_param_count' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 5.4.0.',
        'mysqli_get_metadata' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 5.4.0.',
        'mysqli_send_long_data' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 5.4.0.',
        'ocifreecursor' => 'This alias has been DEPRECATED as of PHP 5.4.0. Relying on this alias is highly discouraged.',
        'magic_quotes_runtime' => 'This alias has been DEPRECATED as of PHP 5.3.0 and REMOVED as of PHP 7.0.0.',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        $functionName = $node->name;
        if (!$functionName instanceof Node\Name) {
            return [];
        }

        $functionNameString = $functionName->toString();



        // Check if function is from root namespace (no namespace prefix)
        // DEBUG: Check qualified status
        $isQualified = $functionName->isQualified();
        $isFullyQualified = $functionName->isFullyQualified();

        // For built-in PHP functions, they should not be qualified
        // But let's be more permissive and only exclude explicitly namespaced calls
        if ($isFullyQualified && str_contains($functionNameString, '\\')) {
            return [];
        }

        // Check relevant aliases
        if (isset(self::RELEVANT_ALIASES[$functionNameString])) {
            $originalFunction = self::RELEVANT_ALIASES[$functionNameString];
            return [
                RuleErrorBuilder::message(
                    sprintf("'%s(...)' is an alias function, consider using '%s(...)' instead.", $functionNameString, $originalFunction)
                )
                ->identifier('function.aliasUsage')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        // Check deprecated aliases
        if (isset(self::DEPRECATED_ALIASES[$functionNameString])) {
            $deprecationMessage = self::DEPRECATED_ALIASES[$functionNameString];
            return [
                RuleErrorBuilder::message($deprecationMessage)
                ->identifier('function.deprecatedAlias')
                ->line($node->getStartLine())
                ->build(),
            ];
        }

        return [];
    }
}