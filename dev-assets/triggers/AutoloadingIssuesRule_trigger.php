<?php declare(strict_types=1);

// Test case 1: Valid - class name matches file name
class ValidClass
{
    public function test(): void
    {
        // This should pass - class name matches file name
    }
}

// Test case 2: Invalid - class name doesn't match file name
class InvalidClassName
{
    public function test(): void
    {
        // This should trigger an error - class name doesn't match file name
    }
}

// Test case 3: PSR-0 naming convention
class Package_Subpackage_Class
{
    public function test(): void
    {
        // This should pass - PSR-0 naming is handled correctly
    }
}

// Test case 4: WordPress naming convention (if file was named class-wordpress-example.php)
class Wordpress_Example
{
    public function test(): void
    {
        // This demonstrates WordPress naming convention support
    }
}

// Test case 5: Multiple classes in one file (should be ignored)
class FirstClass
{
    public function test(): void
    {
        // This file has multiple classes, so rule should ignore it
    }
}

class SecondClass
{
    public function test(): void
    {
        // Second class in same file
    }
}