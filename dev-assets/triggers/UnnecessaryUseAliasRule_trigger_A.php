<?php declare(strict_types=1);

// This file should NOT trigger the UnnecessaryUseAliasRule

// No aliases at all
use Some\Namespace\ClassName;
use Another\Package\Util;
use Vendor\Library\Helper;

// Different aliases
use Some\Namespace\ClassName as MyClassName;
use Another\Package\Util as MyUtil;
use Vendor\Library\Helper as MyHelper;

// Function and constant imports (should be ignored by the rule)
use function strlen as myStrlen;
use const PHP_VERSION as MY_PHP_VERSION;

class TestClass
{
    public function test(): void
    {
        // Using the imported classes
        $obj1 = new ClassName();
        $obj2 = new MyClassName();
        $obj3 = new MyUtil();
        $obj4 = new MyHelper();

        // Using function and constant
        $len = myStrlen('test');
        $version = MY_PHP_VERSION;
    }
}