<?php declare(strict_types=1);

/**
 * Trigger script for UnknownInspectionRule testing
 * This file contains various @phpstan-ignore comments to test the rule
 */

class TestClass
{
    /**
     * @phpstan-ignore deadCode.unusedVariable
     */
    public function validIgnore(): void
    {
        $unusedVariable = 'this should not trigger because of valid ignore';
    }

    /**
     * @phpstan-ignore unknown.identifier
     */
    public function invalidIgnore(): void
    {
        $anotherUnused = 'this should trigger because unknown.identifier is not known';
    }

    /**
     * @phpstan-ignore type.unsafeComparison
     */
    public function anotherValidIgnore(): void
    {
        if ('string' == 123) { // This would normally trigger unsafe comparison
            echo 'unsafe comparison';
        }
    }

    /**
     * @phpstan-ignore nonexistent.rule
     */
    public function anotherInvalidIgnore(): void
    {
        $nonexistent = 'this should trigger because nonexistent.rule is not known';
    }

    /**
     * @phpstan-ignore ambiguousMethodsCallsInArrayMapping
     */
    public function validCamelCaseIgnore(): void
    {
        $array = [1, 2, 3];
        $result = array_map('nonexistentFunction', $array); // This would normally trigger
    }

    /**
     * @phpstan-ignore someRandomUnknownRule
     */
    public function invalidCamelCaseIgnore(): void
    {
        $random = 'this should trigger because someRandomUnknownRule is not known';
    }
}