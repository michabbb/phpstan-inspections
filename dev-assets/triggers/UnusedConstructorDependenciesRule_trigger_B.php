<?php

// This file should trigger the UnusedConstructorDependenciesRule
// It contains a class with unused constructor dependencies

class ExampleClass
{
    private $usedDependency;
    private $unusedDependency;

    public function __construct(object $usedDependency, object $unusedDependency)
    {
        $this->usedDependency = $usedDependency;
        $this->unusedDependency = $unusedDependency;
    }

    public function getDependency(): object
    {
        return $this->usedDependency;
    }
}