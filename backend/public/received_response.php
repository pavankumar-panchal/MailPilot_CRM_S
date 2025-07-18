<?php
// DB Connection
require_once __DIR__ . '/../config/db.php';


// Get active SMTP servers
$smtps = $db->query("SELECT * FROM smtp_servers WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Stats for dashboard
$totalStats = [
    'total_emails' => 0,
    'unsubscribes' => 0,
    'bounces' => 0,
    'replies' => 0
];

function fetchReplies($smtp, $db)
{
    global $totalStats;
    $host = trim($smtp['host']);
    $port = 995; // Secure POP3
    $protocol = 'pop3';
    $encryption = 'ssl';

    // Mailbox connection string
    $mailbox = "{" . $host . ":" . $port . "/" . $protocol . "/" . $encryption . "/novalidate-cert}INBOX";

    // Open mailbox

    $inbox = @imap_open($mailbox, $smtp['email'], $smtp['password']);
    if (!$inbox) {
        return ["error" => imap_last_error()];
    }


    $emails = @imap_search($inbox, 'UNSEEN', SE_UID);
    if (!$emails) {
        imap_close($inbox);
        return ["error" => imap_last_error()];
    }

    // Process in batches
    $batchSize = 20;
    $messages = [
        'regular' => [],
        'unsubscribes' => [],
        'bounces' => []
    ];

    if ($emails) {
        rsort($emails);
        $emails = array_slice($emails, 0, 50); // Increased limit

        foreach ($emails as $email_number) {
            $overview = imap_fetch_overview($inbox, $email_number, 0)[0];
            $body = imap_fetchbody($inbox, $email_number, 1);
            $headers = imap_headerinfo($inbox, $email_number);
            $headers_raw = imap_fetchheader($inbox, $email_number);
            $body_text = quoted_printable_decode($body);

            // Extract from email
            $from_email = '';
            $from_name = '';
            if (isset($headers->from[0]->mailbox) && isset($headers->from[0]->host)) {
                $from_email = $headers->from[0]->mailbox . '@' . $headers->from[0]->host;
            }
            if (isset($headers->from[0]->personal)) {
                $from_name = $headers->from[0]->personal;
            }

            // Check for bounced emails
            $is_bounce = false;
            $bounce_reason = '';
            if (
                stripos($overview->subject ?? '', 'undeliverable') !== false ||
                stripos($overview->subject ?? '', 'returned') !== false ||
                stripos($overview->subject ?? '', 'failure') !== false ||
                stripos($overview->subject ?? '', 'bounce') !== false
            ) {
                $is_bounce = true;
                $bounce_reason = 'Bounced email detected by subject';
            } elseif (preg_match('/X-Failed-Recipients:\s*(.*)/i', $headers_raw, $matches)) {
                $is_bounce = true;
                $bounce_reason = 'Bounced email detected by headers';
            }

            // Check for unsubscribe requests
            $is_unsubscribe = false;
            $unsubscribe_method = '';
            if ($is_bounce) {
                // Skip unsubscribe checks for bounced emails
            } elseif (
                stripos($body_text, 'unsubscribe') !== false ||
                stripos($overview->subject ?? '', 'unsubscribe') !== false
            ) {
                $is_unsubscribe = true;
                $unsubscribe_method = 'Email content';
            } elseif (preg_match('/List-Unsubscribe:\s*(.*)/i', $headers_raw, $matches)) {
                $is_unsubscribe = true;
                $unsubscribe_method = 'List-Unsubscribe header';
            }

            $message = [
                "from" => $from_name,
                "from_email" => $from_email,
                "subject" => $overview->subject ?? '(No Subject)',
                "date" => $overview->date ?? '',
                "body" => $body_text,
                "headers" => $headers_raw,
                "uid" => $overview->uid ?? $email_number,
                "seen" => $overview->seen ?? false,
                "is_unsubscribe" => $is_unsubscribe,
                "unsubscribe_method" => $unsubscribe_method,
                "is_bounce" => $is_bounce,
                "bounce_reason" => $bounce_reason,
                "account_id" => $smtp['id']
            ];

            // Store email in database
            storeEmail($message, $db);

            if ($is_bounce) {
                $messages['bounces'][] = $message;
                $totalStats['bounces']++;
            } elseif ($is_unsubscribe) {
                $messages['unsubscribes'][] = $message;
                $totalStats['unsubscribes']++;
            } else {
                $messages['regular'][] = $message;
                $totalStats['replies']++;
            }
            $totalStats['total_emails']++;
        }
    }

    imap_close($inbox);
    return $messages;
}

function storeEmail($email, $db)
{
    // First store in processed_emails
    $from = $db->real_escape_string($email['from']);
    $from_email = $db->real_escape_string($email['from_email']);
    $subject = $db->real_escape_string($email['subject']);
    $body = $db->real_escape_string($email['body']);
    $headers = $db->real_escape_string($email['headers']);
    $is_unsubscribe = $email['is_unsubscribe'] ? 1 : 0;
    $unsubscribe_method = $db->real_escape_string($email['unsubscribe_method']);
    $is_bounce = $email['is_bounce'] ? 1 : 0;
    $bounce_reason = $db->real_escape_string($email['bounce_reason']);
    $account_id = intval($email['account_id']);
    $uid = $db->real_escape_string($email['uid']);

    // Convert date to MySQL format
    $date_received = date('Y-m-d H:i:s', strtotime($email['date']));

    $query = "INSERT INTO processed_emails 
              (smtp_server_id, from_email, from_name, subject, body, headers, 
               is_unsubscribe, is_bounce, bounce_reason, unsubscribe_method, 
               date_received, uid)
              VALUES 
              ($account_id, '$from_email', '$from', '$subject', '$body', '$headers',
               $is_unsubscribe, $is_bounce, '$bounce_reason', '$unsubscribe_method',
               '$date_received', '$uid')";

    $db->query($query);

    // If unsubscribe, add to unsubscribers table
    if ($is_unsubscribe) {
        $query = "INSERT IGNORE INTO unsubscribers 
                  (email, source, reason)
                  VALUES 
                  ('$from_email', '$unsubscribe_method', 'Email unsubscribe request')";
        $db->query($query);
    }

    // If bounce, add to bounced_emails table
    if ($is_bounce) {
        $query = "INSERT IGNORE INTO bounced_emails 
                  (email, reason, source)
                  VALUES 
                  ('$from_email', '$bounce_reason', 'Email bounce')";
        $db->query($query);
    }

    // Log processing stats
    $processed_count = 1;
    $unsubscribes_count = $is_unsubscribe ? 1 : 0;
    $bounces_count = $is_bounce ? 1 : 0;

    $query = "INSERT INTO email_processing_logs 
              (smtp_server_id, processed_count, unsubscribes_count, bounces_count, processing_time)
              VALUES 
              ($account_id, $processed_count, $unsubscribes_count, $bounces_count, 0.5)";
    $db->query($query);
}

// Replace multiple COUNT queries with a single query
function getDashboardStats($db)
{
    $stats = [
        'total_emails' => 0,
        'unsubscribes' => 0,
        'bounces' => 0,
        'replies' => 0
    ];

    // Single query to get all counts
    $query = "SELECT 
        COUNT(*) as total,
        SUM(is_unsubscribe) as unsubscribes,
        SUM(is_bounce) as bounces,
        SUM(IF(is_unsubscribe = 0 AND is_bounce = 0, 1, 0)) as replies
        FROM processed_emails";

    $result = $db->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_emails'] = $row['total'];
        $stats['unsubscribes'] = $row['unsubscribes'];
        $stats['bounces'] = $row['bounces'];
        $stats['replies'] = $row['replies'];
    }

    return $stats;
}

function getInitials($name)
{
    $parts = explode(' ', $name);
    $initials = '';

    foreach ($parts as $part) {
        if (preg_match('/[a-zA-Z]/', substr($part, 0, 1))) {
            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2)
                break;
        }
    }

    return $initials ?: '?';
}

function formatDate($dateString, $full = false)
{
    if (empty($dateString))
        return '';

    try {
        $date = new DateTime($dateString);
        $now = new DateTime();

        if ($full) {
            return $date->format('M j, Y g:i A');
        }

        $diff = $now->diff($date);

        if ($diff->days === 0) {
            return $date->format('g:i A');
        } elseif ($diff->days === 1) {
            return 'Yesterday';
        } elseif ($diff->days < 7) {
            return $date->format('D');
        } else {
            return $date->format('M j');
        }
    } catch (Exception $e) {
        return $dateString;
    }
}

$dbStats = getDashboardStats($db);
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .email-body-content {
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }

        .progress-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .circle-loader {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 1rem;
        }

        .circle-loader svg {
            transform: rotate(-90deg);
        }

        .circle-loader circle {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
        }

        .circle-bg {
            stroke: #e5e7eb;
        }

        .circle-progress {
            stroke: #3b82f6;
            transition: stroke-dashoffset 0.3s ease;
        }

        .loader-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: bold;
            color: #1f2937;
        }

        .progress-label {
            font-size: 1rem;
            color: #4b5563;
            font-weight: 500;
            margin-top: 1rem;
        }

        .unsubscribe-badge {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .bounce-badge {
            background-color: #fef3c7;
            color: #92400e;
        }

        .email-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .email-item:hover {
            background-color: #f9fafb;
        }

        .account-tab.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .account-tab.active:hover {
            background-color: #2563eb;
        }

        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* .header-gradient {
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
        } */

        .email-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .email-item:hover .email-actions {
            opacity: 1;
        }

        .email-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php require "navbar.php"; ?>

    <div class="container mx-auto px-4 py-6 max-w-7xl">
        <!-- Header with Stats -->
        <div class="mb-8">
            <div class="p-6 text-black mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-bold flex items-center gap-2">
                            <i class="fas fa-envelope"></i> Email Dashboard
                        </h1>
                        <p class="text-sm text-blue-600 mt-1">Manage your email accounts and communications</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="window.location.reload()"
                            class="flex items-center gap-2 bg-white  hover:bg-opacity-30 text-black px-4 py-2 rounded-lg border border-white border-opacity-20 transition">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Account Tabs -->
            <div class="mb-6">
                <div class="flex overflow-x-auto pb-2 gap-2">
                    <?php foreach ($smtps as $index => $smtp):
                        $replies = fetchReplies($smtp, $db);
                        $unreadCount = count($replies['regular'] ?? []) + count($replies['unsubscribes'] ?? []) + count($replies['bounces'] ?? []);
                        ?>
                        <button onclick="switchAccount(<?= $index ?>)"
                            class="account-tab whitespace-nowrap px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-100 transition flex items-center gap-2 <?= $index === 0 ? 'active' : '' ?>">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($smtp['name']) ?>
                            <?php if ($unreadCount > 0): ?>
                                <!-- <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full"><?= $unreadCount ?></span> -->
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Account Content -->
            <?php foreach ($smtps as $index => $smtp):
                $replies = fetchReplies($smtp, $db);
                $accountStats = [
                    'total' => count($replies['regular'] ?? []) + count($replies['unsubscribes'] ?? []) + count($replies['bounces'] ?? []),
                    'replies' => count($replies['regular'] ?? []),
                    'unsubscribes' => count($replies['unsubscribes'] ?? []),
                    'bounces' => count($replies['bounces'] ?? [])
                ];
                ?>
                <div class="account-content <?= $index === 0 ? 'block' : 'hidden' ?>" id="account-<?= $index ?>">
                    <!-- Account Info -->
                    <div
                        class="flex flex-col md:flex-row gap-4 items-start md:items-center mb-6 bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-800 mb-1"><?= htmlspecialchars($smtp['name']) ?></h3>
                            <p class="text-gray-600 text-sm">
                                <i class="fas fa-server mr-1 text-blue-500"></i> <?= htmlspecialchars($smtp['host']) ?>
                                | <i class="fas fa-user mr-1 text-blue-500"></i> <?= htmlspecialchars($smtp['email']) ?>
                            </p>
                        </div>
                        <div class="flex gap-4">
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Total</div>
                                <div class="font-bold text-blue-600"><?= $accountStats['total'] ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Replies</div>
                                <div class="font-bold text-green-600"><?= $accountStats['replies'] ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Unsubs</div>
                                <div class="font-bold text-red-600"><?= $accountStats['unsubscribes'] ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm text-gray-500">Bounces</div>
                                <div class="font-bold text-yellow-600"><?= $accountStats['bounces'] ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($replies['error'])): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <strong>Connection Error:</strong> <?= htmlspecialchars($replies['error']) ?>
                            </div>
                        </div>
                    <?php elseif (empty($replies['regular']) && empty($replies['unsubscribes']) && empty($replies['bounces'])): ?>
                        <div class="text-center py-12 bg-white rounded-lg shadow-sm border border-gray-200">
                            <i class="fas fa-inbox text-gray-300 text-5xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-700 mb-1">No new emails</h3>
                            <p class="text-gray-500">Your inbox is empty</p>
                        </div>
                    <?php else: ?>
                        <!-- Email Lists -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                            <!-- Regular Messages -->
                            <div class="lg:col-span-2">
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                    <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                                        <h3 class="text-lg font-semibold text-gray-700 flex items-center gap-2">
                                            <i class="fas fa-inbox text-blue-500"></i> Messages
                                            (<?= count($replies['regular']) ?>)
                                        </h3>
                                        <div class="flex gap-2">
                                            <button
                                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                <i class="fas fa-filter mr-1"></i> Filter
                                            </button>
                                            <button
                                                class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                <i class="fas fa-sort mr-1"></i> Sort
                                            </button>
                                        </div>
                                    </div>
                                    <div class="email-list divide-y divide-gray-200">
                                        <?php foreach ($replies['regular'] as $reply): ?>
                                            <div class="email-item p-4 cursor-pointer hover:bg-gray-50 transition relative"
                                                onclick="toggleEmailBody(this, <?= $index ?>, '<?= $reply['uid'] ?>')">
                                                <div class="flex items-start gap-3">
                                                    <div
                                                        class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-medium text-sm">
                                                        <?= getInitials($reply['from'] ?: $reply['from_email']) ?>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex justify-between items-baseline">
                                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                                <?= htmlspecialchars($reply['from'] ?: $reply['from_email']) ?>
                                                            </p>
                                                            <p class="text-xs text-gray-500 ml-2">
                                                                <?= formatDate($reply['date']) ?>
                                                            </p>
                                                        </div>
                                                        <p class="text-sm font-medium text-gray-700 truncate mb-1">
                                                            <?= htmlspecialchars($reply['subject']) ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 email-preview">
                                                            <?= htmlspecialchars(substr(strip_tags($reply['body']), 0, 200)) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="email-actions absolute right-4 top-4 flex gap-1">
                                                    <button
                                                        onclick="event.stopPropagation(); markAsRead(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                        class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 p-1 rounded transition">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button
                                                        onclick="event.stopPropagation(); archiveEmail(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                        class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 p-1 rounded transition">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- Email Body -->
                                            <div class="email-body hidden" id="email-body-<?= $index ?>-<?= $reply['uid'] ?>">
                                                <div class="p-4 border-b border-gray-200 bg-gray-50">
                                                    <div class="flex justify-between items-center">
                                                        <div>
                                                            <h4 class="text-sm font-medium text-gray-900">
                                                                <?= htmlspecialchars($reply['subject']) ?>
                                                            </h4>
                                                            <p class="text-xs text-gray-500">
                                                                From:
                                                                <?= htmlspecialchars($reply['from'] ?: $reply['from_email']) ?>
                                                            </p>
                                                        </div>
                                                        <div class="flex gap-2">
                                                            <button
                                                                onclick="replyToEmail('<?= $reply['from_email'] ?>', '<?= htmlspecialchars(addslashes($reply['subject'])) ?>')"
                                                                class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded transition">
                                                                <i class="fas fa-reply mr-1"></i> Reply
                                                            </button>
                                                            <p class="text-xs text-gray-500">
                                                                <?= formatDate($reply['date'], true) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="p-4">
                                                    <div class="email-body-content bg-gray-50 p-3 rounded text-sm text-gray-700">
                                                        <?= nl2br(htmlspecialchars($reply['body'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>




                            <!-- Sidebar with Unsubscribes and Bounces -->
                            <div class="space-y-6">
                                <!-- Unsubscribe Requests -->
                                <?php if (!empty($replies['unsubscribes'])): ?>
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <div class="p-4 border-b border-gray-200 bg-red-50 flex justify-between items-center">
                                            <h3 class="text-lg font-semibold text-red-700 flex items-center gap-2">
                                                <i class="fas fa-user-slash text-red-500"></i> Unsubscribes
                                                (<?= count($replies['unsubscribes']) ?>)
                                            </h3>
                                            <!-- <button onclick="processAllUnsubscribes(<?= $index ?>)"
                                                class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition">
                                                Process All
                                            </button> -->
                                        </div>
                                        <div class="divide-y divide-gray-200">
                                            <?php foreach ($replies['unsubscribes'] as $reply): ?>
                                                <div class="p-4 hover:bg-red-50 transition">
                                                    <div class="flex items-start gap-3">
                                                        <div
                                                            class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                                                            <i class="fas fa-ban"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex justify-between items-baseline">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?= htmlspecialchars($reply['from_email']) ?>
                                                                </p>
                                                                <span class="text-xs px-2 py-1 rounded-full unsubscribe-badge">
                                                                    Unsubscribe
                                                                </span>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                <?= htmlspecialchars($reply['unsubscribe_method']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <!-- <div class="mt-2 flex justify-end gap-2">
                                                        <button
                                                            onclick="event.stopPropagation(); viewEmail(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                            View
                                                        </button>
                                                        <button
                                                            onclick="event.stopPropagation(); processUnsubscribe('<?= $reply['from_email'] ?>', <?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition">
                                                            Process
                                                        </button>
                                                    </div> -->
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Bounced Emails -->
                                <?php if (!empty($replies['bounces'])): ?>
                                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                                        <div class="p-4 border-b border-gray-200 bg-yellow-50 flex justify-between items-center">
                                            <h3 class="text-lg font-semibold text-yellow-700 flex items-center gap-2">
                                                <i class="fas fa-exclamation-triangle text-yellow-500"></i> Bounces
                                                (<?= count($replies['bounces']) ?>)
                                            </h3>
                                            <!-- <button onclick="processAllBounces(<?= $index ?>)"
                                                class="text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded transition">
                                                Process All
                                            </button> -->
                                        </div>
                                        <div class="divide-y divide-gray-200">
                                            <?php foreach ($replies['bounces'] as $reply): ?>
                                                <div class="p-4 hover:bg-yellow-50 transition">
                                                    <div class="flex items-start gap-3">
                                                        <div
                                                            class="flex-shrink-0 w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center">
                                                            <i class="fas fa-exclamation"></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="flex justify-between items-baseline">
                                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                                    <?= htmlspecialchars($reply['from_email']) ?>
                                                                </p>
                                                                <span class="text-xs px-2 py-1 rounded-full bounce-badge">
                                                                    Bounced
                                                                </span>
                                                            </div>
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                <?= htmlspecialchars($reply['bounce_reason']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <!-- <div class="mt-2 flex justify-end gap-2">
                                                        <button
                                                            onclick="event.stopPropagation(); viewEmail(<?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded transition">
                                                            View
                                                        </button>
                                                        <button
                                                            onclick="event.stopPropagation(); processBounce('<?= $reply['from_email'] ?>', <?= $index ?>, '<?= $reply['uid'] ?>')"
                                                            class="text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded transition">
                                                            Remove
                                                        </button>
                                                    </div> -->
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="progress-overlay hidden">
            <div class="circle-loader">
                <svg viewBox="0 0 36 36">
                    <circle class="circle-bg" cx="18" cy="18" r="16" stroke-width="2"></circle>
                    <circle class="circle-progress" cx="18" cy="18" r="16" stroke-width="2" stroke-dasharray="100 100"
                        stroke-dashoffset="100"></circle>
                </svg>
                <div class="loader-text">0%</div>
            </div>
            <div class="progress-label">Processing request...</div>
        </div>

        <!-- Reply Modal -->
        <div id="replyModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Reply to Email</h3>
                    <button onclick="document.getElementById('replyModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4 overflow-y-auto flex-1">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">To:</label>
                        <input type="text" id="replyTo" class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject:</label>
                        <input type="text" id="replySubject" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message:</label>
                        <textarea id="replyMessage" rows="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"></textarea>
                    </div>
                </div>
                <div class="p-4 border-t border-gray-200 flex justify-end gap-2">
                    <button onclick="document.getElementById('replyModal').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button onclick="sendReply()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <i class="fas fa-paper-plane mr-2"></i> Send
                    </button>
                </div>
            </div>
        </div>

        <script>
            // Switch between SMTP accounts
            function switchAccount(index) {
                // Hide all account contents
                document.querySelectorAll('.account-content').forEach(content => {
                    content.classList.add('hidden');
                });

                // Reset all tabs
                document.querySelectorAll('.account-tab').forEach(tab => {
                    tab.classList.remove('active');
                });

                // Show selected account
                document.getElementById(`account-${index}`).classList.remove('hidden');

                // Activate selected tab
                document.querySelectorAll('.account-tab')[index].classList.add('active');
            }

            // Toggle email body visibility
            function toggleEmailBody(element, accountIndex, emailUid) {
                const emailBody = document.getElementById(`email-body-${accountIndex}-${emailUid}`);
                emailBody.classList.toggle('hidden');

                // Mark as read visually
                if (element.classList.contains('bg-blue-50')) {
                    element.classList.remove('bg-blue-50');
                }
            }

            // View email in modal
            function viewEmail(accountIndex, emailUid) {
                const emailBody = document.getElementById(`email-body-${accountIndex}-${emailUid}`);
                alert("Would show email in modal: " + emailUid);
            }

            // Reply to email
            function replyToEmail(to, subject) {
                document.getElementById('replyTo').value = to;
                document.getElementById('replySubject').value = subject.startsWith('Re:') ? subject : 'Re: ' + subject;
                document.getElementById('replyMessage').value = "\n\n--- Original Message ---\n";
                document.getElementById('replyModal').classList.remove('hidden');
            }

            // Send reply
            function sendReply() {
                showLoading();

                // Simulate sending (replace with actual AJAX call)
                setTimeout(() => {
                    hideLoading();
                    document.getElementById('replyModal').classList.add('hidden');
                    alert("Reply sent successfully!");
                }, 1500);
            }

            // Mark email as read
            function markAsRead(accountIndex, emailUid) {
                showLoading();

                // Simulate processing (replace with actual AJAX call)
                setTimeout(() => {
                    hideLoading();
                    const emailElement = document.querySelector(`[onclick*="${emailUid}"]`);
                    if (emailElement) {
                        emailElement.classList.add('bg-blue-50');
                    }
                    alert("Email marked as read");
                }, 800);
            }

            // Archive email
            function archiveEmail(accountIndex, emailUid) {
                showLoading();

                // Simulate processing (replace with actual AJAX call)
                setTimeout(() => {
                    hideLoading();
                    const emailElement = document.querySelector(`[onclick*="${emailUid}"]`);
                    const emailBody = document.getElementById(`email-body-${accountIndex}-${emailUid}`);
                    if (emailElement) emailElement.remove();
                    if (emailBody) emailBody.remove();
                    alert("Email archived");
                }, 800);
            }

            // Process unsubscribe request
            function processUnsubscribe(email, accountIndex, emailUid) {
                if (!confirm(`Are you sure you want to unsubscribe ${email}?`)) return;

                showLoading();

                // Simulate processing (replace with actual AJAX call)
                setTimeout(() => {
                    hideLoading();

                    // Remove from UI
                    const emailElement = document.querySelector(`[onclick*="${emailUid}"]`);
                    if (emailElement) {
                        emailElement.remove();
                    }

                    const emailBody = document.getElementById(`email-body-${accountIndex}-${emailUid}`);
                    if (emailBody) {
                        emailBody.remove();
                    }

                    alert(`${email} has been unsubscribed successfully.`);
                }, 1500);
            }

            // // Process all unsubscribes
            // function processAllUnsubscribes(accountIndex) {
            //     if (!confirm("Process all unsubscribe requests?")) return;
            //     showLoading();

            //     // Simulate processing all
            //     setTimeout(() => {
            //         hideLoading();
            //         alert("All unsubscribe requests processed");
            //         // In a real app, you would refresh this section
            //     }, 2000);
            // }

            // Process bounced email
            function processBounce(email, accountIndex, emailUid) {
                if (!confirm(`Remove ${email} due to bounce?`)) return;

                showLoading();

                // Simulate processing (replace with actual AJAX call)
                setTimeout(() => {
                    hideLoading();

                    // Remove from UI
                    const emailElement = document.querySelector(`[onclick*="${emailUid}"]`);
                    if (emailElement) {
                        emailElement.remove();
                    }

                    const emailBody = document.getElementById(`email-body-${accountIndex}-${emailUid}`);
                    if (emailBody) {
                        emailBody.remove();
                    }

                    alert(`${email} has been removed due to bounce.`);
                }, 1500);
            }

            // Process all bounces
            function processAllBounces(accountIndex) {
                if (!confirm("Process all bounced emails?")) return;
                showLoading();

                // Simulate processing all
                setTimeout(() => {
                    hideLoading();
                    alert("All bounced emails processed");
                    // In a real app, you would refresh this section
                }, 2000);
            }

            // Show loading overlay
            function showLoading() {
                const overlay = document.getElementById('loadingOverlay');
                overlay.classList.remove('hidden');

                // Animate progress
                const circle = overlay.querySelector('.circle-progress');
                const text = overlay.querySelector('.loader-text');
                let progress = 0;

                const interval = setInterval(() => {
                    progress += 5;
                    if (progress > 100) progress = 100;

                    circle.style.strokeDashoffset = 100 - progress;
                    text.textContent = `${progress}%`;

                    if (progress === 100) {
                        clearInterval(interval);
                    }
                }, 75);
            }

            // Hide loading overlay
            function hideLoading() {
                document.getElementById('loadingOverlay').classList.add('hidden');
            }
        </script>
</body>

</html>