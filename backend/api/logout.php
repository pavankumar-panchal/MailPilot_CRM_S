<?php
// Direct API endpoint for logout - bypasses router for authentication
error_log("Direct logout.php endpoint called");

require_once __DIR__ . '/../app/logout.php';
