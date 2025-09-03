<?php

declare(strict_types=1);

// Negative cases: parent::method() should NOT trigger the rule

class BaseClass
{
    public function methodNotInChild(): void
    {
        echo "Method only in parent";
    }
}

class ChildClass extends BaseClass
{
    public function someMethod(): void
    {
        // This should NOT trigger because methodNotInChild is not defined in ChildClass
        parent::methodNotInChild();
    }
}

class RecursiveParent
{
    public function recursiveMethod(): void
    {
        // This should NOT trigger - recursive call to same method
        parent::recursiveMethod();
    }
}

class StaticParent
{
    public static function staticMethod(): void
    {
        echo "Static";
    }
}

class StaticChild extends StaticParent
{
    public static function instanceMethod(): void
    {
        // This should NOT trigger - static context
        parent::staticMethod();
    }
}

class TraitUsingClass
{
    use SomeTrait;

    public function methodInTrait(): void
    {
        // This should NOT trigger - trait context (though traits are skipped anyway)
        parent::methodInTrait();
    }
}