<?php declare(strict_types=1);

namespace App\Test;

class TypesCastingCanBeUsedTrigger
{
    public function testIntvalUsage(): void
    {
        $foo = '123';
        $bar = intval($foo); // Positive: Should suggest (int) $foo
        $baz = intval($foo, 10); // Positive: Should suggest (int) $foo
        $qux = intval($foo, 8); // Negative: Should not suggest, base is not 10

        $complex = '1' . '2';
        $result = intval($complex); // Positive: Should suggest (int) ($complex)

        $ternary = true ? '1' : '0';
        $result2 = intval($ternary); // Positive: Should suggest (int) ($ternary)
    }

    public function testFloatvalUsage(): void
    {
        $foo = '123.45';
        $bar = floatval($foo); // Positive: Should suggest (float) $foo
    }

    public function testStrvalUsage(): void
    {
        $foo = 123;
        $bar = strval($foo); // Positive: Should suggest (string) $foo
    }

    public function testBoolvalUsage(): void
    {
        $foo = 0;
        $bar = boolval($foo); // Positive: Should suggest (bool) $foo
    }

    public function testSettypeUsage(): void
    {
        $foo = '123';
        settype($foo, 'int'); // Positive: Should suggest $foo = (int) $foo
        settype($foo, 'string'); // Positive: Should suggest $foo = (string) $foo
        settype($foo, 'boolean'); // Positive: Should suggest $foo = (bool) $foo
        settype($foo, 'array'); // Positive: Should suggest $foo = (array) $foo
        settype($foo, 'object'); // Negative: Should not suggest, 'object' is not mapped

        $bar = 'test';
        if (true) {
            settype($bar, 'float'); // Positive: Should suggest $bar = (float) $bar
        }
    }

    public function testEncapsedStringUsage(): void
    {
        $foo = 'world';
        $bar = "hello $foo"; // Negative: Not a simple inlined string
        $baz = "{$foo}"; // Positive: Should suggest (string) $foo
        $qux = "$foo"; // Positive: Should suggest (string) $foo

        $num = 123;
        $strNum = "{$num}"; // Positive: Should suggest (string) $num
    }

    public function testNoViolation(): void
    {
        $foo = '123';
        $bar = (int) $foo;
        $baz = (float) $foo;
        $qux = (string) $foo;
        $quux = (bool) $foo;

        $obj = new \stdClass();
        $obj->property = 'value';
        $str = "{$obj->property}"; // Negative: Not a simple variable or expression

        $arr = ['a' => 1];
        $strArr = "{$arr['a']}"; // Negative: Not a simple variable or expression
    }
}
