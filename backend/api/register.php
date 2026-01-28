<?php
// Direct API endpoint for register - bypasses router for authentication
error_log("Direct register.php endpoint called");

// Prevent router handling since this is a direct endpoint
define('ROUTER_HANDLED', true);

require_once __DIR__ . '/../app/register.php';
