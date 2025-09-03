<?php declare(strict_types=1);

// Test file for MockingMethodsCorrectnessRule
// This file contains examples that should trigger the rule

class TestClass
{
    public function existingMethod(): void {}
    final public function finalMethod(): void {}
}

// Positive case 1: willReturn with returnCallback should trigger
$mock = $this->getMockBuilder(TestClass::class)->getMock();
$mock->expects($this->once())
     ->method('existingMethod')
     ->willReturn($this->returnCallback(function() { return 'test'; })); // Should trigger: use will() instead

// Positive case 2: willReturn with returnValue should trigger
$mock->expects($this->once())
     ->method('existingMethod')
     ->willReturn($this->returnValue('test')); // Should trigger: use will() instead

// Positive case 3: method that doesn't exist should trigger
$mock->expects($this->once())
     ->method('nonExistingMethod') // Should trigger: method doesn't exist
     ->will($this->returnValue('test'));

// Positive case 4: final method should trigger
$mock->expects($this->once())
     ->method('finalMethod') // Should trigger: method is final
     ->will($this->returnValue('test'));

// Negative case: correct usage should not trigger
$mock->expects($this->once())
     ->method('existingMethod')
     ->will($this->returnCallback(function() { return 'test'; })); // Correct usage

// Negative case: correct usage with returnValue
$mock->expects($this->once())
     ->method('existingMethod')
     ->will($this->returnValue('test')); // Correct usage