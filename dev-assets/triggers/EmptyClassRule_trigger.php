<?php declare(strict_types=1);

/**
 * Trigger script for EmptyClassRule testing
 *
 * This file contains various empty class scenarios that should trigger the EmptyClassRule:
 * - Empty classes with no properties or methods
 * - Empty enums with no values or methods
 *
 * And scenarios that should NOT trigger the rule:
 * - Abstract parent classes
 * - Classes inheriting from Exception
 * - Interfaces
 * - Deprecated classes
 * - Anonymous classes
 * - Classes with properties, methods, traits, or enum cases
 */

// SHOULD TRIGGER: Empty class with no properties or methods
class EmptyClass {}

// SHOULD TRIGGER: Empty enum with no values or methods
enum EmptyEnum {}

// SHOULD TRIGGER: Another empty class
class AnotherEmptyClass {}

// SHOULD NOT TRIGGER: Class with properties
class ClassWithProperty {
    public string $name;
}

// SHOULD NOT TRIGGER: Class with methods
class ClassWithMethod {
    public function doSomething(): void {}
}

// SHOULD NOT TRIGGER: Class with trait
trait MyTrait {}
class ClassWithTrait {
    use MyTrait;
}

// SHOULD NOT TRIGGER: Enum with values
enum EnumWithValues {
    case VALUE_ONE;
    case VALUE_TWO;
}

// SHOULD NOT TRIGGER: Enum with methods
enum EnumWithMethod {
    public function getValue(): string {
        return 'test';
    }
}

// SHOULD NOT TRIGGER: Abstract parent class (exception case)
abstract class AbstractParent {}

// SHOULD NOT TRIGGER: Class inheriting from Exception (exception case)
class CustomException extends Exception {}

// SHOULD NOT TRIGGER: Class inheriting from abstract parent
class ChildOfAbstract extends AbstractParent {}

// SHOULD NOT TRIGGER: Interface (should be skipped)
interface MyInterface {}

// SHOULD NOT TRIGGER: Deprecated class (should be skipped)
/**
 * @deprecated This class is deprecated
 */
class DeprecatedClass {}

// SHOULD NOT TRIGGER: Anonymous class (should be skipped)
$anonymous = new class {};

// SHOULD NOT TRIGGER: Class with magic methods only
class ClassWithMagicMethods {
    public function __construct() {}
    public function __destruct() {}
}

// SHOULD NOT TRIGGER: Class with promoted constructor property
class ClassWithPromotedProperty {
    public function __construct(public string $name) {}
}

// SHOULD TRIGGER: Empty class after filtering out magic methods
class EmptyAfterMagicFilter {
    public function __construct() {}
    public function __destruct() {}
    public function __toString() {
        return 'test';
    }
}