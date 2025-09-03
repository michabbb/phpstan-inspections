<?php

declare(strict_types=1);

// Negative cases - should NOT trigger errors

// Case 1: Valid array access with integer index
$arrayVar = [1, 2, 3];
$result1 = $arrayVar[0]; // Valid: array supports integer index

// Case 2: Valid array access with string index
$result2 = $arrayVar["key"]; // Valid: array supports string index

// Case 3: Valid string access with integer index
$stringVar = "hello";
$result3 = $stringVar[0]; // Valid: string supports integer index

// Case 4: Valid string access with string index (numeric string)
$result4 = $stringVar["1"]; // Valid: string supports string index

// Case 5: Mixed type (should be ignored)
function getMixed() {
    return rand(0, 1) ? [1, 2, 3] : "string";
}
$mixedVar = getMixed();
$result5 = $mixedVar[0]; // Valid: mixed type is ignored

// Case 6: Object with offset methods
class WithOffsetMethods {
    public function offsetGet($offset) {
        return "value";
    }
    public function offsetSet($offset, $value) {
        // implementation
    }
}
$offsetObject = new WithOffsetMethods();
$result6 = $offsetObject["key"]; // Valid: object has offset methods

// Case 7: Object with magic methods
class WithMagicMethods {
    public function __get($name) {
        return "value";
    }
    public function __set($name, $value) {
        // implementation
    }
}
$magicObject = new WithMagicMethods();
$result7 = $magicObject["property"]; // Valid: object has magic methods

// Case 8: Null index (push operation) - should be handled by other rules
$arrayVar2 = [1, 2, 3];
$result8 = $arrayVar2[]; // Valid: null index is allowed for push operations