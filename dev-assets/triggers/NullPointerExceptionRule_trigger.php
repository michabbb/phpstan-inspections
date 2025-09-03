<?php declare(strict_types=1);

class MyClass
{
    public function doSomething(): string
    {
        return 'done';
    }

    public string $someProperty = 'value';
}

function process(?MyClass $obj, ?array $arr): void
{
    // Positive case 1: Nullable parameter dereferenced without check (MethodCall)
    $obj->doSomething(); // Should trigger

    // Positive case 2: Local variable assigned null, then dereferenced (MethodCall)
    $anotherObj = null;
    $anotherObj->doSomething(); // Should trigger

    // Negative case 1: Null check before dereference (MethodCall)
    if ($obj !== null) {
        $obj->doSomething(); // Should NOT trigger
    }

    // Negative case 2: Nullsafe operator (MethodCall)
    $obj?->doSomething(); // Should NOT trigger

    // Negative case 3: Not nullable (MethodCall)
    $nonNullableObj = new MyClass();
    $nonNullableObj->doSomething(); // Should NOT trigger

    // Positive case 3: Property fetch on nullable object
    $nullablePropertyObj = null;
    echo $nullablePropertyObj->someProperty; // Should trigger

    // Negative case 4: Property fetch with null check
    if ($nullablePropertyObj !== null) {
        echo $nullablePropertyObj->someProperty; // Should NOT trigger
    }

    // Positive case 4: ArrayDimFetch on nullable array
    echo $arr['key']; // Should trigger

    // Negative case 5: ArrayDimFetch with null check
    if ($arr !== null) {
        echo $arr['key']; // Should NOT trigger
    }
}

class AnotherClass
{
    private ?MyClass $myProperty = null;
    private ?array $myArrayProperty = null;

    public function testProperty(): void
    {
        // Positive case 5: Property dereference on nullable class property (MethodCall)
        $this->myProperty->doSomething(); // Should trigger

        // Negative case 6: Property dereference with null check (MethodCall)
        if ($this->myProperty !== null) {
            $this->myProperty->doSomething(); // Should NOT trigger
        }

        // Positive case 6: ArrayDimFetch on nullable class property
        echo $this->myArrayProperty['key']; // Should trigger

        // Negative case 7: ArrayDimFetch with null check
        if ($this->myArrayProperty !== null) {
            echo $this->myArrayProperty['key']; // Should NOT trigger
        }
    }
}
