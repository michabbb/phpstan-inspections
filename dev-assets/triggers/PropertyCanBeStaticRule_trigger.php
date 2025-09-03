<?php declare(strict_types=1);

// Test cases for PropertyCanBeStaticRule
// This rule detects properties that could be static based on heavy initialization

// Case 1: Property with heavy array initialization (should trigger)
class HeavyArrayProperty
{
    private $heavyArray = [
        'strings' => ['string1', 'string2', 'string3'],
        'nested' => [
            'level1' => ['a', 'b', 'c'],
            'level2' => ['d', 'e', 'f']
        ],
        'more_strings' => ['str1', 'str2', 'str3']
    ];
}

// Case 2: Property with multiple nested arrays (should trigger)
class MultipleNestedArrays
{
    private $config = [
        'database' => [
            'host' => 'localhost',
            'port' => 3306,
            'credentials' => [
                'username' => 'user',
                'password' => 'pass'
            ]
        ],
        'cache' => [
            'driver' => 'redis',
            'servers' => [
                'primary' => 'redis1',
                'secondary' => 'redis2'
            ]
        ],
        'logging' => [
            'level' => 'debug',
            'handlers' => ['file', 'console']
        ]
    ];
}

// Case 3: Property with exactly 3 heavy elements (should trigger)
class ExactlyThreeHeavyElements
{
    private $data = [
        'first' => ['a', 'b'],
        'second' => 'string_value',
        'third' => ['x', 'y', 'z']
    ];
}

// Case 4: Property with less than 3 heavy elements (should NOT trigger)
class LightArrayProperty
{
    private $lightArray = [
        'simple' => 'value',
        'another' => [1, 2]
    ];
}

// Case 5: Public property with heavy array (should NOT trigger)
class PublicHeavyProperty
{
    public $publicHeavy = [
        'strings' => ['a', 'b', 'c'],
        'nested' => [
            'level1' => ['x', 'y', 'z'],
            'level2' => ['p', 'q', 'r']
        ],
        'more' => ['1', '2', '3']
    ];
}

// Case 6: Static property (should NOT trigger)
class StaticHeavyProperty
{
    private static $staticHeavy = [
        'data' => ['heavy', 'array', 'content'],
        'more' => [
            'nested' => ['a', 'b', 'c'],
            'strings' => ['x', 'y', 'z']
        ],
        'final' => ['1', '2', '3']
    ];
}

// Case 7: Constant property (should NOT trigger)
class ConstantProperty
{
    private const HEAVY_CONSTANT = [
        'data' => ['a', 'b', 'c'],
        'nested' => ['x', 'y', 'z']
    ];
}

// Case 8: Property with non-array default (should NOT trigger)
class NonArrayProperty
{
    private $simpleString = 'default value';
    private $simpleInt = 42;
}

// Case 9: Inherited property (should NOT trigger)
class ParentClass
{
    protected $inheritedProperty = [
        'data' => ['inherited', 'values'],
        'more' => ['a', 'b', 'c']
    ];
}

class ChildClass extends ParentClass
{
    // This should NOT trigger because it's inherited
    private $inheritedProperty = [
        'data' => ['overridden', 'values'],
        'more' => ['x', 'y', 'z']
    ];
}

// Case 10: Suppressed property (should NOT trigger)
/**
 * @noinspection PropertyCanBeStaticInspection
 */
class SuppressedProperty
{
    private $suppressedHeavy = [
        'data' => ['suppressed', 'content'],
        'nested' => [
            'level1' => ['a', 'b', 'c'],
            'level2' => ['x', 'y', 'z']
        ],
        'more' => ['1', '2', '3']
    ];
}

// Case 11: Mixed heavy and light properties
class MixedProperties
{
    // Should trigger - heavy array
    private $heavyOne = [
        'strings' => ['a', 'b', 'c'],
        'nested' => ['x', 'y', 'z'],
        'more' => [1, 2, 3]
    ];

    // Should NOT trigger - light array
    private $lightOne = ['simple' => 'value'];

    // Should trigger - another heavy array
    private $heavyTwo = [
        'config' => [
            'db' => ['host', 'port'],
            'cache' => ['redis1', 'redis2']
        ],
        'strings' => ['val1', 'val2', 'val3']
    ];
}