<?php

declare(strict_types=1);

function singleReturn(): int
{
    return 1;
}

function twoReturns(bool $condition): int
{
    if ($condition) {
        return 1;
    }
    return 2;
}

function threeReturns(bool $condition1, bool $condition2): int
{
    if ($condition1) {
        return 1;
    }
    if ($condition2) {
        return 2;
    }
    return 3;
}

function fourReturns(bool $condition1, bool $condition2, bool $condition3): int
{
    if ($condition1) {
        return 1;
    }
    if ($condition2) {
        return 2;
    }
    if ($condition3) {
        return 3;
    }
    return 4;
}

function fiveReturns(bool $condition1, bool $condition2, bool $condition3, bool $condition4): int
{
    if ($condition1) {
        return 1;
    }
    if ($condition2) {
        return 2;
    }
    if ($condition3) {
        return 3;
    }
    if ($condition4) {
        return 4;
    }
    return 5;
}

class MyClass
{
    public function methodWithTwoReturns(bool $condition): string
    {
        if ($condition) {
            return 'a';
        }
        return 'b';
    }

    public function methodWithFourReturns(bool $condition1, bool $condition2, bool $condition3): string
    {
        if ($condition1) {
            return 'a';
        }
        if ($condition2) {
            return 'b';
        }
        if ($condition3) {
            return 'c';
        }
        return 'd';
    }

    public function methodWithSixReturns(bool $condition1, bool $condition2, bool $condition3, bool $condition4, bool $condition5): string
    {
        if ($condition1) {
            return 'a';
        }
        if ($condition2) {
            return 'b';
        }
        if ($condition3) {
            return 'c';
        }
        if ($condition4) {
            return 'd';
        }
        if ($condition5) {
            return 'e';
        }
        return 'f';
    }

    abstract public function abstractMethod(): void;
}
