#!/usr/bin/env php
<?php
/**
 * Check current session status
 */

require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/user_filtering.php';

echo "=== Session Status Check ===\n\n";

echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session Save Path: " . session_save_path() . "\n\n";

echo "Session Data:\n";
if (empty($_SESSION)) {
    echo "  (empty - no session data)\n";
} else {
    foreach ($_SESSION as $key => $value) {
        echo "  $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
}

echo "\nCurrent User:\n";
$user = getCurrentUser();
if ($user) {
    echo "  ID: " . $user['id'] . "\n";
    echo "  Email: " . $user['email'] . "\n";
    echo "  Name: " . $user['name'] . "\n";
    echo "  Role: " . $user['role'] . "\n";
} else {
    echo "  (not logged in)\n";
}

// Check for session files
$sessionPath = session_save_path();
if ($sessionPath) {
    echo "\nSession Files in $sessionPath:\n";
    $files = glob($sessionPath . '/sess_*');
    if ($files) {
        echo "  Total sessions: " . count($files) . "\n";
        // Show last 5 modified
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $recent = array_slice($files, 0, 5);
        foreach ($recent as $file) {
            $mtime = date('Y-m-d H:i:s', filemtime($file));
            $size = filesize($file);
            echo "  " . basename($file) . " - Modified: $mtime, Size: $size bytes\n";
        }
    } else {
        echo "  (no session files found)\n";
    }
}

echo "\n=== Check Complete ===\n";
