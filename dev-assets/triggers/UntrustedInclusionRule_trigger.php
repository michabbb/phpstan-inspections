<?php declare(strict_types=1);

// Test cases for UntrustedInclusionRule
// This rule detects untrusted file inclusion that relies on include_path

// These should trigger the rule (relative paths):
include 'file.php';
require 'subdir/file.php';
include_once 'config/settings.php';
require_once 'lib/functions.php';

// These should NOT trigger the rule (absolute paths):
include '/absolute/path/file.php';
require 'C:\windows\file.php';
include_once __DIR__ . '/file.php';
require_once APPLICATION_ROOT . '/file.php';

// These should NOT trigger the rule (not string literals):
include $variable;
require $path . '/file.php';

// Empty path should not trigger:
include '';
require '';

class TestClass {
    public function testMethod() {
        // Relative include in method should trigger:
        include 'class_file.php';

        // Absolute include should not trigger:
        include __DIR__ . '/method_file.php';
    }
}