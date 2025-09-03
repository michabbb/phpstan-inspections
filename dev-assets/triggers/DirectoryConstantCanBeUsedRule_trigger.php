<?php

declare(strict_types=1);

// This file should trigger the DirectoryConstantCanBeUsedRule
$dir = dirname(__FILE__);

// This should NOT trigger the rule
$otherDir = dirname('/some/path');
$anotherDir = __DIR__;