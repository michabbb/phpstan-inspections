<?php

// This should NOT trigger: empty statement inside if
if (true) {
    ;
}

// This should NOT trigger: empty statement inside loop
for ($i = 0; $i < 10; $i++) {
    ;
}

// This should NOT trigger: empty statement inside declare
declare(strict_types=1) {
    ;
}

// This should NOT trigger: echo with more PHP code after
echo "Hello";
echo "World";