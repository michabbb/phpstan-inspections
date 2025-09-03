<?php declare(strict_types=1);

// This file should NOT trigger the ClassReImplementsParentInterfaceRule

interface Contract {}
interface AnotherContract {}

class ParentClass implements Contract {}

class ChildClass extends ParentClass implements AnotherContract {}

// This should also be fine - no parent class
class StandaloneClass implements Contract {}