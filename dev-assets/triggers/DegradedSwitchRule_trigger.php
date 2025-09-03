<?php

// Positive case 1: Switch with only one case, no default (should trigger "behaves as if")
$value = 1;
switch ($value) {
    case 1:
        echo "One";
        break;
}

// Positive case 2: Switch with only one case and default (should trigger "behaves as if-else")
$value = 2;
switch ($value) {
    case 1:
        echo "One";
        break;
    default:
        echo "Other";
        break;
}

// Positive case 3: Switch with only default case (should trigger "only default case")
$value = 3;
switch ($value) {
    default:
        echo "Default";
        break;
}

// Negative case: Normal switch with multiple cases (should not trigger)
$value = 4;
switch ($value) {
    case 1:
        echo "One";
        break;
    case 2:
        echo "Two";
        break;
    case 3:
        echo "Three";
        break;
    default:
        echo "Other";
        break;
}

// Negative case: Empty switch (should not trigger)
$value = 5;
switch ($value) {
}