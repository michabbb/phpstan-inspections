<?php

// Test cases for UniqidMoreEntropyRule

// This should trigger an error - no more_entropy parameter
$id1 = uniqid();

// This should trigger an error - more_entropy explicitly set to false
$id2 = uniqid('prefix', false);

// This should NOT trigger an error - more_entropy set to true
$id3 = uniqid('prefix', true);

// This should NOT trigger an error - variable parameter (can't be statically analyzed)
$moreEntropy = true;
$id4 = uniqid('prefix', $moreEntropy);

// This should NOT trigger an error - more_entropy set to true with different prefix
$id5 = uniqid('', true);

// This should trigger an error - more_entropy set to false with empty prefix
$id6 = uniqid('', false);

// This should NOT trigger an error - more_entropy set to true with variable prefix
$prefix = 'test';
$id7 = uniqid($prefix, true);