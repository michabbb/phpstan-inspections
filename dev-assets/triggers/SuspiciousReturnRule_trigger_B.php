<?php

// Positive case: Return in finally block voids return from try block
function positiveCase1() {
    try {
        return "from try";
    } finally {
        return "from finally"; // This should trigger the rule
    }
}

// Positive case: Return in finally block voids throw from try block
function positiveCase2() {
    try {
        throw new Exception("error");
    } finally {
        return "from finally"; // This should trigger the rule
    }
}

// Positive case: Return in finally block voids return from catch block
function positiveCase3() {
    try {
        throw new Exception("error");
    } catch (Exception $e) {
        return "from catch";
    } finally {
        return "from finally"; // This should trigger the rule
    }
}

// Positive case: Return in finally block voids throw from catch block
function positiveCase4() {
    try {
        throw new Exception("error");
    } catch (Exception $e) {
        throw new RuntimeException("wrapped");
    } finally {
        return "from finally"; // This should trigger the rule
    }
}