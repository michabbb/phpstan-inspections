<?php

// Positive cases - should trigger the rule

function testIssetUndefined() {
    // This should trigger: $undefined is not defined
    if (isset($undefined)) {
        echo "defined";
    }
}

function testEmptyUndefined() {
    // This should trigger: $undefined is not defined
    if (empty($undefined)) {
        echo "empty";
    }
}

function testCoalesceUndefined() {
    // This should trigger: $undefined is not defined
    $value = $undefined ?? 'default';
}

function testMultipleIssetUndefined() {
    // This should trigger for both variables
    if (isset($var1, $var2)) {
        echo "both defined";
    }
}

// Negative cases - should NOT trigger the rule

function testIssetDefined($param) {
    $localVar = 'test';

    // These should NOT trigger: variables are defined
    if (isset($param)) {
        echo "param defined";
    }

    if (isset($localVar)) {
        echo "local defined";
    }
}

function testEmptyDefined() {
    $defined = 'value';

    // This should NOT trigger: variable is defined
    if (empty($defined)) {
        echo "empty";
    }
}

function testCoalesceDefined() {
    $defined = null;

    // This should NOT trigger: variable is defined
    $value = $defined ?? 'default';
}

function testSpecialVariables() {
    // These should NOT trigger: special variables
    if (isset($this)) {
        echo "this is set";
    }

    if (isset($php_errormsg)) {
        echo "error message";
    }
}

class TestClass {
    public function testThis() {
        // This should NOT trigger: $this is always available in methods
        if (isset($this)) {
            echo "this exists";
        }
    }
}