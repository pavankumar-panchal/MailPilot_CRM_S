<?php
/**
 * API Optimization Helpers
 * Provides caching, compression, and performance optimizations for API endpoints
 */

/**
 * Enable output compression (gzip) for responses
 * Reduces bandwidth by 70-90% for JSON responses
 */
function enableCompression() {
    if (!headers_sent() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && 
            strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            ob_start('ob_gzhandler');
        }
    }
}

/**
 * Set HTTP caching headers for GET requests
 * 
 * @param int $maxAge Cache duration in seconds (default: 60)
 * @param bool $mustRevalidate Force revalidation after expiry (default: true)
 */
function setCacheHeaders($maxAge = 60, $mustRevalidate = true) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        // Don't cache POST, PUT, DELETE requests
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        return;
    }

    // Set cache control for GET requests
    $cacheControl = "public, max-age={$maxAge}";
    if ($mustRevalidate) {
        $cacheControl .= ', must-revalidate';
    }
    
    header("Cache-Control: {$cacheControl}");
    
    // Set ETag for conditional requests
    $etag = md5($_SERVER['REQUEST_URI'] . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ''));
    header("ETag: \"{$etag}\"");
    
    // Check if client has fresh cache
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
        trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
        http_response_code(304); // Not Modified
        exit;
    }
    
    // Set Last-Modified header
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
}

/**
 * Optimize JSON response
 * Minifies and compresses JSON output
 * 
 * @param mixed $data Data to encode
 * @param int $cacheAge Cache duration in seconds (0 = no cache)
 */
function sendOptimizedJSON($data, $cacheAge = 60) {
    // Enable compression first
    enableCompression();
    
    // Set caching headers
    if ($cacheAge > 0) {
        setCacheHeaders($cacheAge);
    } else {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    
    // Ensure content type is set
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    // Encode JSON without pretty print for smaller size
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Set appropriate cache duration based on data type
 */
function getCacheDurationForResource($resource) {
    $durations = [
        'workers' => 120,      // 2 minutes
        'campaigns' => 90,     // 90 seconds
        'templates' => 300,    // 5 minutes
        'smtp' => 180,         // 3 minutes
        'settings' => 600,     // 10 minutes
        'static' => 3600,      // 1 hour
    ];
    
    return $durations[$resource] ?? 60; // Default 60 seconds
}

/**
 * Check if request data has been modified
 * Uses ETag and Last-Modified for conditional requests
 * 
 * @param string $etag Current ETag value
 * @return bool True if data was modified
 */
function isModified($etag) {
    // Check ETag
    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $clientEtag = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
        if ($clientEtag === $etag) {
            return false;
        }
    }
    
    return true;
}

/**
 * Minimize JSON response size by removing unnecessary data
 * 
 * @param array $data Data array
 * @param array $fieldsToRemove Fields to exclude from response
 * @return array Minimized data
 */
function minimizeResponse($data, $fieldsToRemove = []) {
    if (!is_array($data)) {
        return $data;
    }
    
    // Default fields to remove if not needed
    $defaultRemove = ['created_at', 'updated_at', 'deleted_at'];
    $fieldsToRemove = array_merge($defaultRemove, $fieldsToRemove);
    
    // Process array of records
    if (isset($data[0]) && is_array($data[0])) {
        return array_map(function($record) use ($fieldsToRemove) {
            foreach ($fieldsToRemove as $field) {
                unset($record[$field]);
            }
            return $record;
        }, $data);
    }
    
    // Process single record
    foreach ($fieldsToRemove as $field) {
        unset($data[$field]);
    }
    
    return $data;
}

/**
 * Add performance timing headers
 */
function addPerformanceHeaders($startTime) {
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    header("X-Response-Time: {$duration}ms");
    header("X-PHP-Memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . "MB");
}
