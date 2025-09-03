<?php

// Positive case: continue inside switch inside loop - should trigger error
for ($i = 0; $i < 10; $i++) {
    switch ($i) {
        case 1:
            echo "One";
            continue; // This should trigger the rule
        case 2:
            echo "Two";
            break;
    }
}

// Another positive case with foreach
foreach ([1, 2, 3] as $value) {
    switch ($value) {
        case 1:
            continue; // This should also trigger
        case 2:
            break;
    }
}

// Another positive case with while
$i = 0;
while ($i < 5) {
    switch ($i) {
        case 1:
            continue; // This should trigger
        case 2:
            break;
    }
    $i++;
}