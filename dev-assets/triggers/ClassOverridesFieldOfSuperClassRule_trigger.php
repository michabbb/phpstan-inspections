<?php declare(strict_types=1);

namespace App\Test;

class ParentClass
{
    private string $privateField = 'parent_private';
    protected string $protectedField = 'parent_protected';
    public string $publicField = 'parent_public';
    public static string $staticField = 'parent_static';
}

class ChildClassPrivateRedefinition extends ParentClass
{
    // Should trigger: private field redefinition
    private string $privateField = 'child_private';
}

class ChildClassProtectedOverride extends ParentClass
{
    // Should trigger: protected field override with same or stronger access
    protected string $protectedField = 'child_protected';
}

class ChildClassPublicOverride extends ParentClass
{
    // Should trigger: public field override with same or stronger access
    public string $publicField = 'child_public';
}

class ChildClassPublicOverrideFromProtected extends ParentClass
{
    // Should trigger: protected field overridden with public access
    public string $protectedField = 'child_public_from_protected';
}

class ChildClassNoIssueProtectedToPrivate extends ParentClass
{
    // Should NOT trigger: weaker access level (private from protected)
    private string $protectedField = 'child_private_from_protected';
}

class ChildClassNoIssuePublicToProtected extends ParentClass
{
    // Should NOT trigger: weaker access level (protected from public)
    protected string $publicField = 'child_protected_from_public';
}

class ChildClassNoIssuePublicToPrivate extends ParentClass
{
    // Should NOT trigger: weaker access level (private from public)
    private string $publicField = 'child_private_from_public';
}

class ChildClassNoIssueNewField extends ParentClass
{
    // Should NOT trigger: new field
    private string $newField = 'new';
}

class ChildClassNoIssueStaticField extends ParentClass
{
    // Should NOT trigger: static field in parent
    public string $staticField = 'child_static';
}

class GrandChildClass extends ChildClassProtectedOverride
{
    // Should trigger: protected field override with same or stronger access (from ChildClassProtectedOverride)
    protected string $protectedField = 'grandchild_protected';
}
