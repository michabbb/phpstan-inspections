<?php declare(strict_types=1);

/**
 * Trigger script for ObGetCleanCanBeUsedRule
 * This file demonstrates patterns that should trigger the rule
 */

// Pattern 1: Basic ob_get_contents() + ob_end_clean() - SHOULD TRIGGER
ob_start();
echo "Hello World";
$content = ob_get_contents(); // Line that should trigger the rule
ob_end_clean(); // This line should trigger the rule

// Pattern 2: Same pattern in a function - SHOULD TRIGGER
function getBufferedContent(): string {
    ob_start();
    echo "Function output";
    $buffered = ob_get_contents(); // Should trigger
    ob_end_clean(); // Should trigger
    return $buffered;
}

// Pattern 3: With variable assignment - SHOULD TRIGGER
ob_start();
echo "Test content";
$myContent = ob_get_contents(); // Should trigger
ob_end_clean(); // Should trigger

// Pattern 4: In a class method - SHOULD TRIGGER
class BufferHandler {
    public function getContent(): string {
        ob_start();
        echo "Class method output";
        $result = ob_get_contents(); // Should trigger
        ob_end_clean(); // Should trigger
        return $result;
    }
}

// Pattern 5: Multiple statements between - SHOULD NOT TRIGGER (rule requires immediate sequence)
ob_start();
echo "First part";
$intermediate = "something";
$content = ob_get_contents(); // Should NOT trigger due to intermediate statement
ob_end_clean();

// Pattern 6: Only ob_end_clean() without ob_get_contents() - SHOULD NOT TRIGGER
ob_start();
echo "Just end clean";
ob_end_clean();

// Pattern 7: Only ob_get_contents() without ob_end_clean() - SHOULD NOT TRIGGER
ob_start();
echo "Just get contents";
$content = ob_get_contents();

// Pattern 8: Correct usage with ob_get_clean() - SHOULD NOT TRIGGER
ob_start();
echo "Correct usage";
$content = ob_get_clean(); // This is the correct way

// Pattern 9: Method call on object (not global function) - SHOULD NOT TRIGGER
class CustomBuffer {
    public function getContents() { return ''; }
    public function endClean() { return; }
}

$buffer = new CustomBuffer();
$content = $buffer->getContents();
$buffer->endClean();