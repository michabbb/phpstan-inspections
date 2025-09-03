<?php

// Negative case 1: continue with explicit level - should NOT trigger
for ($i = 0; $i < 10; $i++) {
    switch ($i) {
        case 1:
            echo "One";
            continue 2; // Correct usage - continues the outer loop
        case 2:
            echo "Two";
            break;
    }
}

// Negative case 2: continue outside switch - should NOT trigger
for ($i = 0; $i < 10; $i++) {
    if ($i === 5) {
        continue; // This is fine - continues the loop normally
    }
    echo $i;
}

// Negative case 3: switch not inside loop - should NOT trigger
$value = 1;
switch ($value) {
    case 1:
        continue; // This is fine - not in a loop
    case 2:
        break;
}

// Negative case 4: continue 2 in switch inside loop - should NOT trigger
foreach ([1, 2, 3] as $item) {
    switch ($item) {
        case 1:
            continue 2; // Correct usage
        case 2:
            break;
    }
}

// Negative case 5: break in switch - should NOT trigger
for ($i = 0; $i < 5; $i++) {
    switch ($i) {
        case 1:
            break; // This is fine - breaks out of switch
        case 2:
            echo "Two";
            break;
    }
}