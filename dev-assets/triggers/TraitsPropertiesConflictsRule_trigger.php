<?php declare(strict_types=1);

// This file demonstrates trait property conflicts that should be detected by TraitsPropertiesConflictsRule

trait ExampleTrait {
    public string $conflictingProperty = 'default';
    public int $anotherProperty = 42;
}

class ParentClass {
    public string $parentProperty = 'parent';
    protected string $protectedProperty = 'protected';
}

class ConflictingClass extends ParentClass {
    use ExampleTrait;

    // This should trigger a conflict warning because both class and trait define $conflictingProperty
    public string $conflictingProperty = 'default';

    // This should NOT trigger because the names are different
    public string $differentProperty = 'different';
}

class NonConflictingClass extends ParentClass {
    use ExampleTrait;

    // This should NOT trigger because the default values are different
    public string $conflictingProperty = 'different_default';

    // This should NOT trigger because it's a different property
    public string $uniqueProperty = 'unique';
}