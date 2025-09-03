<?php

class ParentClass {
    public function method(string $parameter): string {
        return $parameter;
    }
}

class ChildClass extends ParentClass {
    // This should trigger the rule - senseless proxy method
    public function method(string $parameter): string {
        return parent::method($parameter);
    }
}