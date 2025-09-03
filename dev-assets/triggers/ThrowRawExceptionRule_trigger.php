<?php declare(strict_types=1);

namespace App\Test;

class CustomException extends \Exception
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class AnotherCustomException extends \Exception
{
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// Test function 1: Throwing raw \Exception
function testRawException(): void
{
    throw new \Exception('Something went wrong');
}

// Test function 2: Throwing raw Exception (without leading backslash)
function testRawExceptionNoBackslash(): void
{
    throw new Exception('Another error');
}

// Test function 3: Throwing custom exception without message
function testCustomExceptionNoMessage(): void
{
    throw new CustomException();
}

// Test function 4: Throwing specific SPL exception (should be OK)
function testSpecificException(): void
{
    throw new \RuntimeException('This is fine');
}

// Test function 5: Throwing custom exception with message (should be OK)
function testCustomExceptionWithMessage(): void
{
    throw new CustomException('Custom error message');
}

// Test function 6: Throwing custom exception with arguments (should be OK)
function testCustomExceptionWithArgs(): void
{
    throw new AnotherCustomException('Message', 123);
}
