<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: https://payrollsoft.in");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../config/db.php';

// Get request path
$fullPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$request = str_replace($basePath, '', $fullPath);

// Remove query params
$request = preg_replace('/\?.*/', '', $request);
$request = rtrim($request, '/'); // remove trailing slash
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch (true) {
        case ($request === '/api/upload'):
            require __DIR__ . '/../public/email_processor.php';
            break;

        case ($request === '/api/results'):
            require __DIR__ . '/../includes/get_results.php';
            break;

        case ($request === '/api/monitor/campaigns' && $method === 'GET'):
            require __DIR__ . '/../includes/monitor_campaigns.php';
            break;

        case ($request === '/api/master/campaigns'):
            require __DIR__ . '/../includes/campaign.php';
            break;

        case ($request === '/api/master/campaigns_master'):
            require __DIR__ . '/../public/campaigns_master.php';
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
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
