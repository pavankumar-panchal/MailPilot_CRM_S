<?php
// Direct API endpoint for login - bypasses router for authentication
error_log("Direct login.php endpoint called");

// Prevent router handling since this is a direct endpoint
define('ROUTER_HANDLED', true);

require_once __DIR__ . '/../app/login.php';
