<?php

// This file contains examples that should trigger the MisorderedModifiersRule

class TestClass
{
    // Positive cases - should trigger the rule

    // Wrong order: static before final
    static final public function wrongOrder1(): void {}

    // Wrong order: static before public
    static public function wrongOrder2(): void {}

    // Wrong order: private before static
    private static function wrongOrder3(): void {}

    // Wrong order: protected before static
    protected static function wrongOrder4(): void {}

    // Wrong order: static before visibility
    static public function wrongOrder5(): void {}

    // Multiple wrong orders
    static private function wrongOrder6(): void {}

    // Negative cases - should NOT trigger the rule

    // Correct order: final, visibility, static
    final public static function correctOrder1(): void {}

    // Only one modifier - no ordering issue possible
    public function singleModifier1(): void {}
    private function singleModifier2(): void {}
    protected function singleModifier3(): void {}
    static function singleModifier4(): void {}
    final function singleModifier5(): void {}

    // No relevant modifiers - should be ignored
    public function noRelevantModifiers(): void {}

    // Correct order with fewer modifiers
    final public function correctOrder2(): void {}
    public static function correctOrder3(): void {}
    final static function correctOrder4(): void {}

    // Additional valid combinations
    final protected static function correctOrder5(): void {}
}