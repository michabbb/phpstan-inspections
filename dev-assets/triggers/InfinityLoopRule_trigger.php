<?php declare(strict_types=1);

/**
 * Trigger script for InfinityLoopRule testing
 * This file contains test cases that should trigger the InfinityLoopRule
 */

class TestInfinityLoop
{
    // POSITIVE CASES - Should trigger the rule

    /**
     * Method that returns recursive call to $this
     * Should trigger: InfinityLoopRule
     */
    public function recursiveThis(): mixed
    {
        return $this->recursiveThis();
    }

    /**
     * Method that calls self recursively
     * Should trigger: InfinityLoopRule
     */
    public function recursiveSelf(): void
    {
        self::recursiveSelf();
    }

    /**
     * Method that calls static recursively
     * Should trigger: InfinityLoopRule
     */
    public function recursiveStatic(): void
    {
        static::recursiveStatic();
    }

    // NEGATIVE CASES - Should NOT trigger the rule

    /**
     * Method with multiple statements
     * Should NOT trigger: InfinityLoopRule
     */
    public function multipleStatements(): void
    {
        echo "First statement";
        $this->multipleStatements();
    }

    /**
     * Method calling different method
     * Should NOT trigger: InfinityLoopRule
     */
    public function callsDifferentMethod(): mixed
    {
        return $this->recursiveThis();
    }

    /**
     * Method with proper termination condition
     * Should NOT trigger: InfinityLoopRule
     */
    public function withTermination($count = 0): mixed
    {
        if ($count > 10) {
            return null;
        }
        return $this->withTermination($count + 1);
    }

    /**
     * Abstract method (should be skipped)
     */
    abstract public function abstractMethod(): void;

    /**
     * Method without body (should be skipped)
     */
    public function noBodyMethod();
}