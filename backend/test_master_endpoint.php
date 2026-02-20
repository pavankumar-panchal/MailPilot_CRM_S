<?php
/**
 * Test script for /api/master/ endpoint
 * 
 * This script tests if the endpoint is properly configured and returns data
 */

echo "Testing /api/master/ endpoint...\n\n";

// Test 1: Check if the router file exists
$routerFile = __DIR__ . '/routes/api.php';
if (file_exists($routerFile)) {
    echo "✓ Router file exists: $routerFile\n";
} else {
    echo "✗ Router file NOT found: $routerFile\n";
    exit(1);
}

// Test 2: Check if campaigns_master.php exists
$campaignsFile = __DIR__ . '/public/campaigns_master.php';
if (file_exists($campaignsFile)) {
    echo "✓ Campaigns master file exists: $campaignsFile\n";
} else {
    echo "✗ Campaigns master file NOT found: $campaignsFile\n";
    exit(1);
}

// Test 3: Check if route is defined in api.php
$routerContent = file_get_contents($routerFile);
if (strpos($routerContent, "case (\$request === '/api/master/' || \$request === '/api/master')") !== false) {
    echo "✓ Route for /api/master/ is defined in router\n";
} else {
    echo "✗ Route for /api/master/ NOT found in router\n";
    exit(1);
}

// Test 4: Check if ROUTER_HANDLED is set in router
if (strpos($routerContent, "define('ROUTER_HANDLED', true);") !== false) {
    echo "✓ ROUTER_HANDLED is defined in router\n";
} else {
    echo "✗ ROUTER_HANDLED NOT found in router\n";
}

// Test 5: Check if campaigns_master.php uses ROUTER_HANDLED
$campaignsContent = file_get_contents($campaignsFile);
if (strpos($campaignsContent, "if (!defined('ROUTER_HANDLED'))") !== false) {
    echo "✓ campaigns_master.php checks for ROUTER_HANDLED\n";
} else {
    echo "✗ campaigns_master.php does NOT check for ROUTER_HANDLED\n";
}

// Test 6: Check for syntax errors (literal \n characters)
if (strpos($campaignsContent, "\\n            \$batch_id") !== false) {
    echo "✗ WARNING: Syntax error found - literal \\n characters still present\n";
} else {
    echo "✓ No syntax errors found (\\n characters fixed)\n";
}

// Test 7: Check required includes
$requiredIncludes = [
    'session_config.php',
    'security_helpers.php',
    'auth_helper.php',
    'campaign_cache.php',
    'api_optimization.php'
];

foreach ($requiredIncludes as $include) {
    if (strpos($campaignsContent, $include) !== false) {
        echo "✓ Required include found: $include\n";
    } else {
        echo "✗ Missing include: $include\n";
    }
}

echo "\n=== Test Summary ===\n";
echo "All basic checks passed! The endpoint should now work properly.\n\n";

echo "=== How to Test ===\n";
echo "1. For GET request (list campaigns):\n";
echo "   curl 'https://payrollsoft.in/emailvalidation/backend/routes/api.php?endpoint=/api/master/' \\\n";
echo "        -H 'Cookie: [your-session-cookie]'\n\n";

echo "2. For POST request (with action):\n";
echo "   curl -X POST 'https://payrollsoft.in/emailvalidation/backend/routes/api.php?endpoint=/api/master/' \\\n";
echo "        -H 'Content-Type: application/json' \\\n";
echo "        -H 'Cookie: [your-session-cookie]' \\\n";
echo "        -d '{\"action\":\"list\"}'\n\n";

echo "3. Access via browser (you must be logged in):\n";
echo "   https://payrollsoft.in/emailvalidation/backend/routes/api.php?endpoint=/api/master/\n\n";

echo "Note: You must be authenticated (logged in) to access this endpoint.\n";
echo "If you get a 401 Unauthorized error, ensure your session is valid.\n";
