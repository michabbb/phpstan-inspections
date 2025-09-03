<?php declare(strict_types=1);

ini_set('define_syslog_variables', '0'); // Deprecated in PHP 5.3, removed in PHP 5.4
ini_get('magic_quotes_gpc'); // Deprecated in PHP 5.3, removed in PHP 5.4
ini_alter('xsl.security_prefs', '0'); // Deprecated in PHP 5.4, removed in PHP 7.0
ini_restore('safe_mode'); // Deprecated in PHP 5.3, removed in PHP 5.4

ini_set('display_errors', '1'); // Not deprecated
ini_get('memory_limit'); // Not deprecated
