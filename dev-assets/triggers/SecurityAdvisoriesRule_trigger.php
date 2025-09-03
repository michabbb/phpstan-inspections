<?php declare(strict_types=1);

// Trigger script for SecurityAdvisoriesRule
// This file should be analyzed by PHPStan to trigger the SecurityAdvisoriesRule

// Simple function call to trigger the rule analysis
function testSecurityAdvisories(): void
{
    // This function call will trigger the rule to check composer.json
    echo "Testing security advisories rule";
}

// Call the function to ensure analysis happens
testSecurityAdvisories();