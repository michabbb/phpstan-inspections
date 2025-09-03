<?php declare(strict_types=1);

// This file contains examples that should trigger the PassingByReferenceCorrectnessRule

// Function that takes a parameter by reference
function modifyByReference(&$value): void {
    $value = 'modified';
}

// Function that returns by reference
function &getReference(): string {
    static $ref = 'reference';
    return $ref;
}

// Function that does NOT return by reference
function getValue(): string {
    return 'value';
}

// Class with methods that take parameters by reference
class ReferenceHandler {
    public function modifyByReference(&$value): void {
        $value = 'modified by method';
    }

    public function &getReference(): string {
        static $ref = 'method reference';
        return $ref;
    }

    public function getValue(): string {
        return 'method value';
    }
}

// POSITIVE CASES - These should trigger the rule

// 1. Passing function call that doesn't return by reference to by-reference parameter
modifyByReference(getValue()); // ERROR: getValue() doesn't return by reference

// 2. Passing method call that doesn't return by reference to by-reference parameter
$handler = new ReferenceHandler();
$handler->modifyByReference($handler->getValue()); // ERROR: getValue() doesn't return by reference

// 3. Passing new expression to by-reference parameter
modifyByReference(new stdClass()); // ERROR: new expressions should not be passed by reference

// 4. Passing method call with new expression to by-reference parameter
$handler->modifyByReference(new ReferenceHandler()); // ERROR: new expressions in method calls

// 5. Passing literal values to by-reference parameters
modifyByReference('literal string'); // ERROR: literals cannot be passed by reference
modifyByReference(42); // ERROR: literals cannot be passed by reference

// NEGATIVE CASES - These should NOT trigger the rule

// 1. Passing variable to by-reference parameter (correct)
$variable = 'test';
modifyByReference($variable); // OK: variables can be passed by reference

// 2. Passing function call that DOES return by reference
modifyByReference(getReference()); // OK: getReference() returns by reference

// 3. Passing method call that DOES return by reference
$handler->modifyByReference($handler->getReference()); // OK: getReference() returns by reference

// 4. Passing variable to method by-reference parameter
$methodVar = 'method test';
$handler->modifyByReference($methodVar); // OK: variables can be passed by reference