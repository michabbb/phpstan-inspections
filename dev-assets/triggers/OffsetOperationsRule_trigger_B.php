<?php

declare(strict_types=1);

// Positive cases - should trigger errors

// Case 1: Accessing offset on integer (unsupported)
$intVar = 42;
$result1 = $intVar[0]; // Should trigger: integer doesn't support offset operations

// Case 2: Accessing offset on float (unsupported)
$floatVar = 3.14;
$result2 = $floatVar[1]; // Should trigger: float doesn't support offset operations

// Case 3: Accessing offset on boolean (unsupported)
$boolVar = true;
$result3 = $boolVar[0]; // Should trigger: boolean doesn't support offset operations

// Case 4: String access with incompatible index type (float)
$stringVar = "hello";
$result4 = $stringVar[1.5]; // Should trigger: float index incompatible with string

// Case 5: Array access with incompatible index type (boolean)
$arrayVar = [1, 2, 3];
$result5 = $arrayVar[true]; // Should trigger: boolean index incompatible with array

// Case 6: Object without offset methods
class NoOffsetMethods {
    public string $property = "test";
}
$objectVar = new NoOffsetMethods();
$result6 = $objectVar[0]; // Should trigger: object doesn't support offset operations

// Case 7: Function call result with unsupported type
function getInt(): int {
    return 42;
}
$result7 = getInt()[0]; // Should trigger: function returns int, doesn't support offsets