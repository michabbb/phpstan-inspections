<?php

class ParentClass {
    public function method(string $parameter): string {
        return $parameter;
    }

    public function anotherMethod(int $param): int {
        return $param * 2;
    }
}

class ChildClass extends ParentClass {
    // This should NOT trigger - adds functionality
    public function method(string $parameter): string {
        $processed = strtoupper($parameter);
        return parent::method($processed);
    }

    // This should NOT trigger - different parameter order
    public function anotherMethod(int $param): int {
        return parent::anotherMethod($param + 1);
    }

    // This should NOT trigger - private method
    private function privateMethod(string $param): string {
        return parent::method($param);
    }

    // This should NOT trigger - abstract in parent
    public function abstractMethod(string $param): string {
        return parent::method($param);
    }

    // This should NOT trigger - different return type
    public function methodWithDifferentReturn(string $param): int {
        return (int) parent::method($param);
    }
}

// Abstract parent for testing
abstract class AbstractParent {
    abstract public function abstractMethod(string $param): string;

    public function concreteMethod(string $param): string {
        return $param;
    }
}

class ConcreteChild extends AbstractParent {
    // This should NOT trigger - implements abstract method
    public function abstractMethod(string $param): string {
        return parent::abstractMethod($param);
    }

    // This should trigger - senseless proxy of concrete method
    public function concreteMethod(string $param): string {
        return parent::concreteMethod($param);
    }
}