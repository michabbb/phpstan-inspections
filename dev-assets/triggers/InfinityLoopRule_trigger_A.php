<?php declare(strict_types=1);

class TestClass
{
    // This should NOT trigger - different method name
    public function getValue(): string
    {
        return $this->getOtherValue();
    }

    // This should NOT trigger - multiple statements
    public function getOtherValue(): int
    {
        $value = 42;
        return $value;
    }

    // This should NOT trigger - calls different class method
    public function getStaticValue(): float
    {
        return OtherClass::getStaticValue();
    }

    // This should NOT trigger - abstract method
    abstract public function abstractMethod(): void;

    // This should NOT trigger - calls parent method
    public function getParentValue(): string
    {
        return parent::getParentValue();
    }

    // This should NOT trigger - calls method on different object
    public function getObjectValue(): string
    {
        $obj = new OtherClass();
        return $obj->getValue();
    }
}

class OtherClass
{
    public static function getStaticValue(): float
    {
        return 3.14;
    }

    public function getValue(): string
    {
        return 'value';
    }
}