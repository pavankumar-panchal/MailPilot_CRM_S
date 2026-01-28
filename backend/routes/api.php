<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');

error_log("=== API Router Start === Request: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));

try {
    // Use centralized session configuration
    require_once __DIR__ . '/../includes/session_config.php';
    error_log("Session config loaded successfully");
} catch (Exception $e) {
    error_log("FATAL: Session config failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Session initialization failed', 'details' => $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Dynamic CORS for local dev ports and production with credentials
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('/^https?:\/\/payrollsoft\.in/', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (preg_match('/^http:\/\/localhost:(5173|5174|5175|5176)$/', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (preg_match('/^http:\/\/192\.168\.\d+\.\d+(:\d+)?$/', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Default for production
    header('Access-Control-Allow-Origin: https://payrollsoft.in');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../config/db.php';
    error_log("Database config loaded successfully");
} catch (Exception $e) {
    error_log("FATAL: Database config failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database initialization failed', 'details' => $e->getMessage()]);
    exit;
}

// Get request path from query parameter
$request = $_GET['endpoint'] ?? '';
$request = '/' . ltrim($request, '/'); // Ensure leading slash
$method = $_SERVER['REQUEST_METHOD'];

error_log("API Router - Method: $method, Endpoint: $request");
error_log("API Router - Query string: " . ($_SERVER['QUERY_STRING'] ?? 'none'));
error_log("API Router - Full _GET: " . json_encode($_GET));

try {
    switch (true) {
        case ($request === '/api/register' && $method === 'POST'):
            error_log("API Router: Processing register request");
            error_log("API Router: Database connection exists: " . (isset($conn) ? 'YES' : 'NO'));
            // Skip session/cors/headers since router already handled
            define('ROUTER_HANDLED', true);
            require __DIR__ . '/../app/register.php';
            break;

        case ($request === '/api/login' && $method === 'POST'):
            error_log("API Router: Processing login request");
            error_log("API Router: Database connection exists: " . (isset($conn) ? 'YES' : 'NO'));
            // Skip session/cors/headers since router already handled
            define('ROUTER_HANDLED', true);
            $loginFile = __DIR__ . '/../app/login.php';
            if (!file_exists($loginFile)) {
                error_log("FATAL: login.php not found at: $loginFile");
                http_response_code(500);
                echo json_encode(['error' => 'Login endpoint not found']);
                exit;
            }
            error_log("API Router: Requiring login.php from: $loginFile");
            try {
                require $loginFile;
            } catch (Exception $e) {
                error_log("FATAL: Login execution failed: " . $e->getMessage());
                error_log("FATAL: Stack trace: " . $e->getTraceAsString());
                http_response_code(500);
                echo json_encode(['error' => 'Login execution failed', 'details' => $e->getMessage()]);
            }
            break;

        case ($request === '/api/logout' && $method === 'POST'):
            require __DIR__ . '/../app/logout.php';
            break;

        case ($request === '/api/verify_session' && $method === 'GET'):
            require __DIR__ . '/../app/verify_session.php';
            break;

        case ($request === '/api/set_session' && $method === 'POST'):
            require __DIR__ . '/../app/set_session.php';
            break;

        case ($request === '/api/upload'):
            require __DIR__ . '/../public/email_processor.php';
            break;

        case ($request === '/api/results'):
            define('ROUTER_HANDLED', true);
            require __DIR__ . '/../includes/get_results.php';
            break;

        case ($request === '/api/monitor/campaigns' && $method === 'GET'):
            require __DIR__ . '/../includes/monitor_campaigns.php';
            break;

        case ($request === '/api/master/campaigns_master'):
            error_log("API Router: Routing to campaigns_master.php");
            require __DIR__ . '/../public/campaigns_master.php';
            break;

        case ($request === '/api/master/campaigns' || strpos($request, '/api/master/campaigns?') === 0):
            // Handle /api/master/campaigns with or without ?id=X parameter
            error_log("API Router: Routing to campaign.php");
            require __DIR__ . '/../includes/campaign.php';
            break;

        case ($request === '/api/master/campaigns/start' && $method === 'POST'):
            require __DIR__ . '/../includes/start_campaign.php';
            break;

        case ($request === '/api/master/smtps'):
            require __DIR__ . '/../includes/master_smtps.php';
            break;

        case ($request === '/api/master/distribution'):
            require __DIR__ . '/../includes/campaign_distribution.php';
            break;

        case ($request === '/api/retry-failed' && $method === 'POST'):
            // Retry logic can be added later if needed
            echo json_encode(['status' => 'info', 'message' => 'Retry endpoint available for future use']);
            break;

        case ($request === '/api/master/email-counts'):
            $result = $conn->query("
                SELECT
                    COUNT(*) AS total_valid,
                    SUM(CASE WHEN mb.status IS NULL OR mb.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN mb.status = 'success' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN mb.status = 'failed' THEN 1 ELSE 0 END) AS failed
                FROM emails e
                LEFT JOIN mail_blaster mb ON mb.to_mail = e.raw_emailid
                WHERE e.domain_status = 1
            ");
            $row = $result->fetch_assoc();
            echo json_encode([
                'total_valid' => (int)$row['total_valid'],
                'pending' => (int)$row['pending'],
                'sent' => (int)$row['sent'],
                'failed' => (int)$row['failed'],
            ]);
            break;

        case ($request === '/api/workers'):
            require __DIR__ . '/../includes/workers.php';
            break;

        case ($request === '/api/received-response'):
        case ($request === '/api/emails'):
        case (strpos($request, '/api/emails') === 0):
            require __DIR__ . '/../app/received_response.php';
            break;

        case (preg_match('#^/api/master/smtps/(\d+)/accounts/(\d+)$#', $request, $m) ? true : false):
            $_GET['smtp_server_id'] = $m[1];
            $_GET['account_id'] = $m[2];
            require __DIR__ . '/../includes/smtp_accounts.php';
            break;

        case (preg_match('#^/api/master/smtps/(\d+)/accounts$#', $request, $m) ? true : false):
            $_GET['smtp_server_id'] = $m[1];
            require __DIR__ . '/../includes/smtp_accounts.php';
            break;

        default:
            error_log("API Router - No matching route for: $request");
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found', 'request' => $request]);
            break;
    }
} catch (Exception $e) {
    error_log("API Router - Exception: " . $e->getMessage());
    error_log("API Router - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
