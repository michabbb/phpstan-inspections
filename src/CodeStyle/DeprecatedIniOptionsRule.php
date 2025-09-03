<?php declare(strict_types=1);

namespace macropage\PHPStan\Inspections\CodeStyle;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Detects usage of deprecated or removed PHP INI configuration options.
 *
 * This rule analyzes calls to ini_get(), ini_set(), ini_alter(), and ini_restore() functions
 * and reports when deprecated or removed INI configuration options are being used.
 * It provides version information and suggests alternatives where available.
 *
 * @implements \PHPStan\Rules\Rule<\PhpParser\Node\Expr\FuncCall>
 */
final class DeprecatedIniOptionsRule implements Rule
{
    private const TARGET_FUNCTIONS = [
        'ini_set',
        'ini_get',
        'ini_alter',
        'ini_restore',
    ];

    /**
     * @var array<string, array{?int, ?int, ?string}>
     *   Key: ini directive name (lowercase)
     *   Value: [deprecationVersionId, removalVersionId, alternative]
     *   Version IDs are in the format PHP_MAJOR * 10000 + PHP_MINOR * 100 + PHP_RELEASE
     *   null for version means not applicable
     *   null for alternative means no alternative
     */
    private const DEPRECATED_INI_OPTIONS = [
        // http://php.net/manual/en/network.configuration.php
        'define_syslog_variables' => [50300, 50400, null],
        // http://php.net/manual/en/info.configuration.php
        'magic_quotes_gpc' => [50300, 50400, null],
        'magic_quotes_runtime' => [50300, 50400, null],
        // http://php.net/manual/en/misc.configuration.php
        'highlight.bg' => [null, 50400, null],
        // http://php.net/manual/en/xsl.configuration.php
        'xsl.security_prefs' => [50400, 70000, 'XsltProcessor->setSecurityPrefs()'],
        // http://php.net/manual/en/ini.sect.safe-mode.php
        'safe_mode' => [50300, 50400, null],
        'safe_mode_gid' => [50300, 50400, null],
        'safe_mode_include_dir' => [50300, 50400, null],
        'safe_mode_exec_dir' => [50300, 50400, null],
        'safe_mode_allowed_env_vars' => [50300, 50400, null],
        'safe_mode_protected_env_vars' => [50300, 50400, null],
        // http://php.net/manual/en/ini.core.php
        'sql.safe_mode' => [null, 70200, null],
        'asp_tags' => [null, 70000, null],
        'always_populate_raw_post_data' => [50600, 70000, null],
        'y2k_compliance' => [null, 50400, null],
        'zend.ze1_compatibility_mode' => [null, 50300, null],
        'allow_call_time_pass_reference' => [50300, 50400, null],
        'register_globals' => [50300, 50400, null],
        'register_long_arrays' => [50300, 50400, null],
        // http://php.net/manual/en/session.configuration.php
        'session.hash_function' => [null, 70100, null],
        'session.hash_bits_per_character' => [null, 70100, null],
        'session.entropy_file' => [null, 70100, null],
        'session.entropy_length' => [null, 70100, null],
        'session.bug_compat_42' => [null, 50400, null],
        'session.bug_compat_warn' => [null, 50400, null],
        // http://php.net/manual/en/iconv.configuration.php
        'iconv.input_encoding' => [50600, null, 'default_charset'],
        'iconv.output_encoding' => [50600, null, 'default_charset'],
        'iconv.internal_encoding' => [50600, null, 'default_charset'],
        // http://php.net/manual/en/mbstring.configuration.php
        'mbstring.script_encoding' => [null, 50400, 'zend.script_encoding'],
        'mbstring.func_overload' => [70200, null, null],
        'mbstring.internal_encoding' => [50600, null, 'default_charset'],
        'mbstring.http_input' => [50600, null, 'default_charset'],
        'mbstring.http_output' => [50600, null, 'default_charset'],
        // http://php.net/manual/en/sybase.configuration.php
        'magic_quotes_sybase' => [50300, 50400, null],
        // https://www.php.net/manual/en/errorfunc.configuration.php
        'track_errors' => [70200, null, null],
        // https://www.php.net/manual/en/ref.pdo-odbc.php
        'pdo_odbc.db2_instance_name' => [70300, null, null],
        // https://www.php.net/manual/en/opcache.configuration.php
        'opcache.load_comments' => [null, 70000, null],
        'opcache.fast_shutdown' => [null, 70200, null],
        'opcache.inherited_hack' => [null, 70300, null],
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

        $functionName = $this->getFunctionName($node, $scope);
        if ($functionName === null || !\in_array($functionName, self::TARGET_FUNCTIONS, true)) {
            return [];
        }

        $args = $node->getArgs();
        if (\count($args) === 0) {
            return [];
        }

        $firstArgValue = $args[0]->value;
        if (!$firstArgValue instanceof String_) {
            return [];
        }

        $directive = \strtolower($firstArgValue->value);

        if (!\array_key_exists($directive, self::DEPRECATED_INI_OPTIONS)) {
            return [];
        }

        [$deprecationVersionId, $removalVersionId, $alternative] = self::DEPRECATED_INI_OPTIONS[$directive];

        $errors = [];
        $currentPhpVersionId = PHP_VERSION_ID;

        if ($removalVersionId !== null && $currentPhpVersionId >= $removalVersionId) {
            $message = \sprintf(
                '\'%s\' was removed in PHP %s%s.',
                $directive,
                $this->formatVersionId($removalVersionId),
                $alternative !== null ? ' Use ' . $alternative . ' instead' : ''
            );
            $errors[] = RuleErrorBuilder::message($message)->identifier('iniOption.removed')->line($node->getStartLine())->build();
        } elseif ($deprecationVersionId !== null && $currentPhpVersionId >= $deprecationVersionId) {
            $message = \sprintf(
                '\'%s\' is deprecated since PHP %s%s.',
                $directive,
                $this->formatVersionId($deprecationVersionId),
                $alternative !== null ? ' Use ' . $alternative . ' instead' : ''
            );
            $errors[] = RuleErrorBuilder::message($message)->identifier('iniOption.deprecated')->line($node->getStartLine())->build();
        }

        return $errors;
    }

    private function getFunctionName(FuncCall $node, Scope $scope): ?string
    {
        if ($node->name instanceof Node\Name) {
            return $scope->resolveName($node->name);
        }

        return null;
    }

    private function formatVersionId(int $versionId): string
    {
        $major = (int) ($versionId / 10000);
        $minor = (int) (($versionId % 10000) / 100);
        // The Java code seems to only care about major.minor for the message,
        // so we'll format it similarly.
        return \sprintf('%d.%d', $major, $minor);
    }
}
