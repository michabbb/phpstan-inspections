<?php declare(strict_types=1);

// Test case for UnusedUseStatementRule - should trigger unused use statement errors

use DateTime; // unused - should trigger
use stdClass; // unused - should trigger  
use ArrayObject as ArrObj; // unused alias - should trigger
use PDO as Database; // unused alias - should trigger
use Exception; // used below - should NOT trigger
use SplFileInfo as FileInfo; // used below - should NOT trigger

function testFunction(): void
{
    throw new Exception('This uses Exception');
    $file = new FileInfo(__FILE__); // This uses FileInfo alias
}