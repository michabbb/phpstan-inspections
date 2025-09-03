<?php

// This file should NOT trigger the UnusedConstructorDependenciesRule
// All constructor dependencies are used

class ValidExampleClass
{
    private $usedDependency;
    private $anotherUsedDependency;

    public function __construct(object $usedDependency, object $anotherUsedDependency)
    {
        $this->usedDependency = $usedDependency;
        $this->anotherUsedDependency = $anotherUsedDependency;
    }

    public function getDependency(): object
    {
        return $this->usedDependency;
    }

    public function getAnotherDependency(): object
    {
        return $this->anotherUsedDependency;
    }
}

// Class with annotated property (should be ignored)
class AnnotatedClass
{
    /** @var object */
    private $annotatedDependency;

    public function __construct(object $annotatedDependency)
    {
        $this->annotatedDependency = $annotatedDependency;
    }
}

// Class with public property (should be ignored)
class PublicPropertyClass
{
    public $publicDependency;

    public function __construct(object $publicDependency)
    {
        $this->publicDependency = $publicDependency;
    }
}