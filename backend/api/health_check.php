<?php
/**
 * Quick Health Check Endpoint
 * Access: /api/health_check.php
 * 
 * Fast response with basic server status
 * Use this to test if APIs are responding
 */

// Prevent any HTML output
ob_start();

$startTime = microtime(true);

// Set headers immediately
header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
];

// Quick DB ping
try {
    $dbPath = __DIR__ . '/../config/db.php';
    if (!file_exists($dbPath)) {
        throw new Exception('db.php not found at: ' . $dbPath);
    }
    
    require_once $dbPath;
    
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection object not created');
    }
    
    $health['database'] = $conn->ping() ? 'connected' : 'disconnected';
    
    // Quick query test
    $startQuery = microtime(true);
    $result = $conn->query("SELECT 1");
    $queryTime = (microtime(true) - $startQuery) * 1000; // ms
    
    $health['db_query_ms'] = round($queryTime, 2);
    
    if ($queryTime > 100) {
        $health['status'] = 'slow';
        $health['warning'] = 'Database query took ' . round($queryTime) . 'ms';
    }
    
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['database'] = 'error';
    $health['db_error'] = $e->getMessage();
    $health['db_trace'] = $e->getFile() . ':' . $e->getLine();
}

$health['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

// Clear any accidental output
ob_end_clean();

echo json_encode($health, JSON_PRETTY_PRINT);
