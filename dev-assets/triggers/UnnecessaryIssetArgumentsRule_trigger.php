<?php

// Positive cases - should trigger the UnnecessaryIssetArgumentsRule

function testRedundantArrayBase() {
    $array = ['key' => 'value'];

    // This should trigger: $array is redundant since $array['key'] covers it
    if (isset($array, $array['key'])) {
        echo "both exist";
    }
}

function testRedundantNestedArray() {
    $data = ['level1' => ['level2' => 'value']];

    // This should trigger: $data and $data['level1'] are redundant
    // since $data['level1']['level2'] covers them
    if (isset($data, $data['level1'], $data['level1']['level2'])) {
        echo "nested exists";
    }
}

function testRedundantMultipleLevels() {
    $matrix = [['item1' => 'value1'], ['item2' => 'value2']];

    // This should trigger: $matrix and $matrix[0] are redundant
    // since $matrix[0]['item1'] covers them
    if (isset($matrix, $matrix[0], $matrix[0]['item1'])) {
        echo "matrix exists";
    }
}

function testRedundantWithVariables() {
    $config = ['database' => ['host' => 'localhost']];
    $dbKey = 'database';
    $hostKey = 'host';

    // This should trigger: $config is redundant since $config[$dbKey] covers it
    if (isset($config, $config[$dbKey])) {
        echo "config exists";
    }

    // This should trigger: $config and $config[$dbKey] are redundant
    // since $config[$dbKey][$hostKey] covers them
    if (isset($config, $config[$dbKey], $config[$dbKey][$hostKey])) {
        echo "host exists";
    }
}

// Negative cases - should NOT trigger the rule

function testNonRedundantArguments() {
    $array1 = ['a' => 1];
    $array2 = ['b' => 2];

    // This should NOT trigger: different arrays, no redundancy
    if (isset($array1['a'], $array2['b'])) {
        echo "different arrays";
    }
}

function testNonArrayArguments() {
    $var1 = 'test';
    $var2 = 42;

    // This should NOT trigger: non-array variables, no redundancy possible
    if (isset($var1, $var2)) {
        echo "variables exist";
    }
}

function testSingleArgument() {
    $array = ['key' => 'value'];

    // This should NOT trigger: only one argument
    if (isset($array['key'])) {
        echo "single argument";
    }
}

function testUnrelatedArrayAccess() {
    $data = ['a' => 1, 'b' => 2];

    // This should NOT trigger: different keys, no redundancy
    if (isset($data['a'], $data['b'])) {
        echo "different keys";
    }
}

function testComplexNonRedundant() {
    $structure = [
        'users' => ['admin' => ['name' => 'Admin']],
        'config' => ['debug' => true]
    ];

    // This should NOT trigger: accessing different branches
    if (isset($structure['users']['admin']['name'], $structure['config']['debug'])) {
        echo "different branches";
    }
}