<?php declare(strict_types=1);

class TestClass
{
    // This should trigger the infinity loop rule
    public function getValue(): string
    {
        return $this->getValue(); // Infinite recursion
    }

    // This should also trigger
    public function getOtherValue(): int
    {
        return self::getOtherValue(); // Infinite recursion with self
    }

    // This should also trigger
    public function getStaticValue(): float
    {
        return static::getStaticValue(); // Infinite recursion with static
    }

    // This should also trigger (expression statement)
    public function processData(): void
    {
        $this->processData(); // Infinite recursion
    }
}