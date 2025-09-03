<?php declare(strict_types=1);

namespace TestNamespace;

use PHPUnit\Framework\TestCase;

/**
 * Test class to trigger PHPUnitTestsRule
 */
class PHPUnitTestsRuleTrigger extends TestCase
{
    /**
     * Valid data provider method
     */
    public static function validDataProvider(): array
    {
        return [
            ['data1'],
            ['data2'],
        ];
    }

    /**
     * Valid test method with data provider
     * @dataProvider validDataProvider
     */
    public function testValidDataProvider(string $data): void
    {
        $this->assertNotEmpty($data);
    }

    /**
     * Invalid: @dataProvider referencing non-existing method
     * @dataProvider nonExistingDataProvider
     */
    public function testInvalidDataProvider(string $data): void
    {
        $this->assertNotEmpty($data);
    }

    /**
     * Valid dependency method
     */
    public function testDependencyMethod(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Invalid: @depends referencing non-existing method
     * @depends nonExistingDependencyMethod
     */
    public function testInvalidDepends(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Valid: @depends referencing existing method
     * @depends testDependencyMethod
     */
    public function testValidDepends(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Invalid: @covers referencing non-existing class
     * @covers NonExistingClass
     */
    public function testInvalidCoversClass(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Invalid: @covers referencing non-existing method
     * @covers TestNamespace\PHPUnitTestsRuleTrigger::nonExistingMethod
     */
    public function testInvalidCoversMethod(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Valid: @covers referencing existing class
     * @covers TestNamespace\PHPUnitTestsRuleTrigger
     */
    public function testValidCovers(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Invalid: @test annotation on method that starts with 'test' (ambiguous)
     * @test
     */
    public function testAmbiguousAnnotation(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Valid: @test annotation on method that doesn't start with 'test'
     * @test
     */
    public function shouldBeValidTestAnnotation(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Valid: method starting with 'test' without @test annotation
     */
    public function testValidWithoutAnnotation(): void
    {
        $this->assertTrue(true);
    }

    /**
     * Data provider that could benefit from named datasets
     */
    public static function dataProviderWithoutNames(): array
    {
        return [
            ['value1', 'expected1'],
            ['value2', 'expected2'],
        ];
    }

    /**
     * Test using data provider that could use named datasets
     * @dataProvider dataProviderWithoutNames
     */
    public function testDataProviderSuggestion(string $input, string $expected): void
    {
        $this->assertEquals($expected, $input);
    }
}