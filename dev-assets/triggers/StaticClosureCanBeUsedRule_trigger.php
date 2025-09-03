<?php

declare(strict_types=1);

function make(): callable
{
    $x = 10;
    return function () use ($x) { // should trigger: can be static
        return $x + 1;
    };
}

