<?php declare(strict_types=1);

namespace TestNamespace;

// Test cases for UnqualifiedReferenceRule
// This file should trigger the rule for unqualified function and constant references

class UnqualifiedReferenceTest
{
    public function testUnqualifiedFunctions(): void
    {
        // These should trigger the rule (unqualified function calls)
        $length = strlen('test'); // Should suggest \strlen()
        $count = count([1, 2, 3]); // Should suggest \count()
        $isArray = is_array([]); // Should suggest \is_array()
        $isString = is_string('test'); // Should suggest \is_string()
        $defined = defined('TEST_CONSTANT'); // Should suggest \defined()
        $functionExists = function_exists('test'); // Should suggest \function_exists()
        $extensionLoaded = extension_loaded('test'); // Should suggest \extension_loaded()
        $constant = constant('TEST_CONSTANT'); // Should suggest \constant()
        $arrayKeyExists = array_key_exists('key', []); // Should suggest \array_key_exists()
        $isScalar = is_scalar('test'); // Should suggest \is_scalar()
        $sprintf = sprintf('%s', 'test'); // Should suggest \sprintf()
    }

    public function testUnqualifiedConstants(): void
    {
        // These should trigger the rule (unqualified constant references)
        $version = PHP_VERSION; // Should suggest \PHP_VERSION
        $os = PHP_OS; // Should suggest \PHP_OS
        $eol = PHP_EOL; // Should suggest \PHP_EOL
        $intMax = PHP_INT_MAX; // Should suggest \PHP_INT_MAX
    }

    public function testStringCallbacks(): void
    {
        // These should trigger the rule (unqualified string callbacks)
        $result1 = array_map('strlen', ['a', 'bb', 'ccc']); // Should suggest \strlen
        $result2 = array_filter([1, 2, 3], 'is_int'); // Should suggest \is_int
        $result3 = call_user_func('count', [1, 2]); // Should suggest \count
        $result4 = call_user_func_array('sprintf', ['%s %s', 'hello', 'world']); // Should suggest \sprintf
    }

    public function testFalsePositives(): void
    {
        // These should NOT trigger the rule (false positives)
        $true = true;
        $false = false;
        $null = null;
        $line = __LINE__;
        $file = __FILE__;
        $dir = __DIR__;
        $function = __FUNCTION__;
        $class = __CLASS__;
        $method = __METHOD__;
        $namespace = __NAMESPACE__;
    }

    public function testQualifiedReferences(): void
    {
        // These should NOT trigger the rule (already qualified)
        $length = \strlen('test');
        $count = \count([1, 2, 3]);
        $version = \PHP_VERSION;
        $result = \array_map('\strlen', ['a', 'bb']);
    }

    public function testImportedFunctions(): void
    {
        // These should NOT trigger if functions are imported
        // (though we can't test imports in this simple file)
    }
}