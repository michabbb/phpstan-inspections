<?php

declare(strict_types=1);

// This file contains examples that should trigger the FopenBinaryUnsafeUsageRule

// Case 1: Missing 'b' modifier (should trigger)
$handle1 = fopen('file.txt', 'r');

// Case 2: 'b' modifier in wrong position (should trigger)
$handle2 = fopen('file.txt', 'br');

// Case 3: 't' modifier present (should trigger)
$handle3 = fopen('file.txt', 'rt');

// Case 4: Correct usage with 'b' at the end (should NOT trigger)
$handle4 = fopen('file.txt', 'rb');

// Case 5: Correct usage with 'b+' at the end (should NOT trigger)
$handle5 = fopen('file.txt', 'wb+');

// Case 6: Write mode without 'b' (should trigger)
$handle6 = fopen('file.txt', 'w');

// Case 7: Append mode without 'b' (should trigger)
$handle7 = fopen('file.txt', 'a');

// Case 8: Complex mode with 'b' misplaced (should trigger)
$handle8 = fopen('file.txt', 'r+b');

// Case 9: Empty mode (should NOT trigger - edge case)
$handle9 = fopen('file.txt', '');

// Case 10: Non-string literal mode (should NOT trigger our rule)
$mode = 'r';
$handle10 = fopen('file.txt', $mode);