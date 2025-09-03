<?php

// Negative case: Return in finally block but no return/throw in try block
function negativeCase1() {
    try {
        echo "no return here";
    } finally {
        return "from finally"; // This should NOT trigger the rule
    }
}

// Negative case: Return in finally block but no return/throw in catch blocks
function negativeCase2() {
    try {
        throw new Exception("error");
    } catch (Exception $e) {
        echo "no return here";
    } finally {
        return "from finally"; // This should NOT trigger the rule
    }
}

// Negative case: No finally block
function negativeCase3() {
    try {
        return "from try";
    } catch (Exception $e) {
        return "from catch";
    }
    // No finally block, so no issue
}

// Negative case: Finally block without return statement
function negativeCase4() {
    try {
        return "from try";
    } finally {
        echo "cleanup"; // No return here
    }
}

// Negative case: Return in try block, but finally has no return
function negativeCase5() {
    try {
        return "from try";
    } finally {
        echo "cleanup without return";
    }
}