<?php
declare(strict_types=1);

// Test cases for TestRule - this rule reports ALL function calls

// Case 1: Simple function calls (should trigger multiple errors)
echo "Hello World\n";
print_r(['test' => 'data']);
var_dump("debug info");

// Case 2: Built-in PHP functions (should trigger errors)
$array = array_merge([1, 2], [3, 4]);
$string = implode(', ', $array);
$length = strlen($string);
$uppercase = strtoupper($string);

// Case 3: String functions (should trigger errors)
$haystack = "The quick brown fox";
$needle = "fox";
$position = strpos($haystack, $needle);
$substring = substr($haystack, 0, 10);
$replaced = str_replace("fox", "dog", $haystack);

// Case 4: Array functions (should trigger errors)
$numbers = [5, 2, 8, 1, 9];
sort($numbers);
$filtered = array_filter($numbers, fn($n) => $n > 3);
$mapped = array_map('strtoupper', ['a', 'b', 'c']);
$unique = array_unique([1, 2, 2, 3, 3, 3]);

// Case 5: Math functions (should trigger errors)
$max = max($numbers);
$min = min($numbers);
$random = rand(1, 100);
$rounded = round(3.14159, 2);
$absolute = abs(-42);

// Case 6: Date/time functions (should trigger errors)
$timestamp = time();
$formatted = date('Y-m-d H:i:s', $timestamp);
$parsed = strtotime('2023-01-01');

// Case 7: File functions (should trigger errors)
$exists = file_exists(__FILE__);
$contents = file_get_contents(__FILE__);
$info = pathinfo(__FILE__);
$dirname = dirname(__FILE__);

// Case 8: JSON functions (should trigger errors)
$data = ['name' => 'John', 'age' => 30];
$json = json_encode($data);
$decoded = json_decode($json, true);

// Case 9: Type checking functions (should trigger errors)
$value = "123";
$isString = is_string($value);
$isNumeric = is_numeric($value);
$isEmpty = empty($value);
$isSet = isset($value);

// Case 10: Custom functions (should trigger errors)
function customFunction($param) {
    return "Custom: $param";
}

$result = customFunction("test");

// Case 11: Conditional function calls (should trigger errors)
if (function_exists('mb_strlen')) {
    $mbLength = mb_strlen("multibyte string");
}

// Case 12: Function calls in expressions (should trigger errors)
$total = count([1, 2, 3]) + strlen("hello");
$condition = in_array('needle', ['hay', 'stack', 'needle']) && !empty($array);

// Case 13: Function calls with complex parameters (should trigger errors)
$complex = array_merge(
    array_filter([1, 2, 3, 4, 5], fn($n) => $n % 2 === 0),
    array_map(fn($n) => $n * 2, [10, 20, 30])
);

// Case 14: Nested function calls (should trigger multiple errors)
$nested = strtoupper(trim(substr("  Hello World  ", 2, 10)));

// Case 15: Function calls in loops (should trigger errors for each iteration)
foreach (range(1, 3) as $i) {
    echo sprintf("Number: %d\n", $i);
    usleep(1000);
}

// Case 16: Function calls in try-catch (should trigger errors)
try {
    $result = json_decode('invalid json');
    throw new Exception("Test exception");
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Case 17: Anonymous functions with function calls (should trigger errors)
$callback = function($item) {
    return strtoupper($item);
};

$processed = array_map($callback, ['a', 'b', 'c']);

// Case 18: Method calls (should NOT trigger - TestRule only handles function calls)
$obj = new stdClass();
$obj->property = "value";

class TestClass {
    public function testMethod() {
        // Function calls inside methods should still trigger
        return strlen("test");
    }
}

$testObj = new TestClass();
$methodResult = $testObj->testMethod();

// Case 19: Static method calls (should NOT trigger - not function calls)
$className = TestClass::class;

// Case 20: Variable functions (should trigger error)
$functionName = 'strlen';
$varResult = $functionName("variable function");

// Every function call in this file should be reported by the TestRule