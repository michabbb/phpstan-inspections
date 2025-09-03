<?php

declare(strict_types=1);

function pathOp(string $p): string
{
    return dirname(dirname($p)); // should trigger: use dirname($p, 2)
}

