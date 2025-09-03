<?php declare(strict_types=1);

// Using global namespace to avoid test context detection

// Base class (level 1)
class BaseClass {
    public function baseMethod(): void {}
}

// Level 2 - extends BaseClass
class Level2Class extends BaseClass {
    public function level2Method(): void {}
}

// Level 3 - extends Level2Class
class Level3Class extends Level2Class {
    public function level3Method(): void {}
}

// Level 4 - extends Level3Class (should trigger the rule - 4 levels total)
class Level4Class extends Level3Class {
    public function level4Method(): void {}
}

// Another example with 5 levels
class AnotherBase {
    public function anotherBaseMethod(): void {}
}

class AnotherLevel2 extends AnotherBase {
    public function anotherLevel2Method(): void {}
}

class AnotherLevel3 extends AnotherLevel2 {
    public function anotherLevel3Method(): void {}
}

class AnotherLevel4 extends AnotherLevel3 {
    public function anotherLevel4Method(): void {}
}

class AnotherLevel5 extends AnotherLevel4 {
    public function anotherLevel5Method(): void {}
}

// Test class that should NOT trigger (only 2 levels)
class ShortInheritance extends BaseClass {
    public function shortMethod(): void {}
}

// Concrete class that should trigger (4 levels)
class DeepInheritanceA extends BaseClass {
    public function methodA(): void {}
}

class DeepInheritanceB extends DeepInheritanceA {
    public function methodB(): void {}
}

class DeepInheritanceC extends DeepInheritanceB {
    public function methodC(): void {}
}

class DeepInheritanceD extends DeepInheritanceC {
    public function methodD(): void {}
}