<?php

declare(strict_types=1);

// TRIGGER CASE 1: Calling parent method that current class doesn't own
class BaseClass
{
    public function baseMethod(): void
    {
        echo 'Base method';
    }
    
    public static function staticBaseMethod(): void
    {
        echo 'Static base method';
    }
}

class ChildClass extends BaseClass
{
    public function triggerMethod(): void
    {
        // SHOULD TRIGGER: parent::baseMethod() → $this->baseMethod()
        // - Different method names ✓
        // - Child doesn't own baseMethod ✓  
        // - Not overridden by children ✓
        parent::baseMethod();
        
        // SHOULD TRIGGER: parent::staticBaseMethod() → self::staticBaseMethod()
        // - Different method names ✓
        // - Child doesn't own staticBaseMethod ✓
        // - Not overridden by children ✓
        parent::staticBaseMethod();
    }
    
    public function __construct()
    {
        // SHOULD NOT TRIGGER: parent::__construct() in __construct()
        // - Same method names (blocked by rule)
        parent::__construct();
    }
}

// TRIGGER CASE 2: Another scenario
class ParentWithUtil
{
    public function utilityMethod(): string
    {
        return 'utility';
    }
}

class ChildWithUtil extends ParentWithUtil
{
    public function doSomething(): void
    {
        // SHOULD TRIGGER: parent::utilityMethod() → $this->utilityMethod()
        // - Different method names ✓
        // - Child doesn't own utilityMethod ✓
        // - Not overridden by children ✓
        $result = parent::utilityMethod();
        echo $result;
    }
}

// NON-TRIGGER CASE: Child owns the method
class ParentOwned
{
    public function sharedMethod(): void
    {
        echo 'Parent version';
    }
}

class ChildOwned extends ParentOwned
{
    public function sharedMethod(): void
    {
        // SHOULD NOT TRIGGER: Child owns sharedMethod
        parent::sharedMethod();
        echo 'Child version';
    }
    
    public function caller(): void
    {
        // SHOULD NOT TRIGGER: Child owns sharedMethod
        parent::sharedMethod();
    }
}