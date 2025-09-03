<?php declare(strict_types=1);

// This file should trigger the ClassReImplementsParentInterfaceRule

interface Contract {}

class ParentClass implements Contract {}

class ChildClass extends ParentClass implements Contract {}