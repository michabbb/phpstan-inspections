<?php

declare(strict_types=1);

namespace TestNamespace;

class TestReturnTypeRule
{
    // Should suggest: string
    public function getName()
    {
        return 'John Doe';
    }

    // Should suggest: int
    public function getAge()
    {
        return 25;
    }

    // Should suggest: ?string (nullable)
    public function getOptionalName()
    {
        if (rand(0, 1)) {
            return 'Optional';
        }
        return null;
    }

    // Should suggest: void
    public function doSomething()
    {
        echo 'Hello';
    }

    // Should suggest: \Generator
    public function generateNumbers()
    {
        for ($i = 0; $i < 10; $i++) {
            yield $i;
        }
    }

    // Should suggest: array
    public function getData()
    {
        return ['key' => 'value'];
    }

    // Should suggest: bool
    public function isValid()
    {
        return true;
    }

    // Should suggest: float
    public function getPrice()
    {
        return 19.99;
    }

    // Abstract method with @return annotation - should suggest: string
    /**
     * @return string
     */
    abstract public function getAbstractName();

    // Abstract method with @return annotation - should suggest: int
    /**
     * @return int
     */
    abstract public function getAbstractAge();

    // Method with mixed returns - should not suggest (complex case)
    public function getMixed()
    {
        if (rand(0, 1)) {
            return 'string';
        }
        return 42;
    }

    // Method with no return statement - should suggest: void
    public function process()
    {
        $this->doSomething();
    }

    // Method with implicit null return - should suggest: ?string
    public function getConditionalString()
    {
        if (false) {
            return 'never reached';
        }
        // implicit null return
    }

    // Magic method - should be ignored
    public function __toString()
    {
        return 'TestReturnTypeRule';
    }

    // Method with existing return type - should be ignored
    public function getExistingType(): string
    {
        return 'already typed';
    }
}