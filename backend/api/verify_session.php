<?php
// Direct API endpoint for verify_session - bypasses router for authentication
error_log("Direct verify_session.php endpoint called");

require_once __DIR__ . '/../app/verify_session.php';
