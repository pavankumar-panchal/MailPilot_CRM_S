#!/usr/bin/env php
<?php
/**
 * Database Connection and Schema Verification Script
 * Tests connection to production database and verifies required tables exist
 * 
 * Production Database: email_id (MariaDB 5.5.68)
 */

echo "============================================\n";
echo "Database Connection & Schema Verification\n";
echo "Production Server: payrollsoft.in\n";
echo "============================================\n\n";

// Include database configuration
require_once __DIR__ . '/../config/db.php';

echo "✓ Database connection successful!\n";
echo "✓ Character set: " . $conn->character_set_name() . "\n\n";

// Test required tables from production schema
$required_tables = [
    'campaign_master' => 'Campaign definitions',
    'campaign_status' => 'Campaign execution status',
    'mail_blaster' => 'Email queue for sending',
    'smtp_accounts' => 'SMTP account credentials',
    'smtp_servers' => 'SMTP server configurations',
    'smtp_usage' => 'SMTP usage tracking',
    'smtp_rotation' => 'SMTP rotation state',
    'emails' => 'Validated email addresses',
    'csv_list' => 'Uploaded email lists',
    'processed_emails' => 'Received email responses',
    'bounced_emails' => 'Bounced email tracking',
    'unsubscribers' => 'Unsubscribe requests',
    'exclude_domains' => 'Excluded domains',
    'exclude_accounts' => 'Excluded accounts',
    'workers' => 'Processing workers',
    'email_processing_logs' => 'Email processing history',
    'stats_cache' => 'Statistics cache',
    'campaign_distribution' => 'Campaign SMTP distribution'
];

echo "Checking required tables:\n";
echo str_repeat('-', 80) . "\n";

$missing_tables = [];
foreach ($required_tables as $table => $description) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ $table - EXISTS ($description)\n";
        
        // Get row count
        $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($count_result) {
            $count = $count_result->fetch_assoc()['count'];
            echo "  └─ Rows: " . number_format($count) . "\n";
        }
    } else {
        echo "✗ $table - MISSING ($description)\n";
        $missing_tables[] = $table;
    }
}

echo "\n" . str_repeat('-', 80) . "\n";

if (!empty($missing_tables)) {
    echo "\n⚠ WARNING: Missing tables: " . implode(', ', $missing_tables) . "\n";
    echo "Please import the database schema!\n\n";
}

// Check smtp_accounts columns (important for email sending)
echo "\nVerifying smtp_accounts structure:\n";
$smtp_cols = $conn->query("SHOW COLUMNS FROM smtp_accounts");
if ($smtp_cols) {
    $cols_found = [];
    while ($col = $smtp_cols->fetch_assoc()) {
        $cols_found[] = $col['Field'];
    }
    
    $required_cols = ['id', 'smtp_server_id', 'email', 'password', 'daily_limit', 'hourly_limit', 
                      'is_active', 'sent_today', 'total_sent', 'created_at'];
    
    foreach ($required_cols as $col) {
        if (in_array($col, $cols_found)) {
            echo "  ✓ $col\n";
        } else {
            echo "  ✗ $col - MISSING\n";
        }
    }
}

// Check campaign_master structure
echo "\n" . str_repeat('=', 80) . "\n";
echo "Recent Campaigns:\n";
echo str_repeat('-', 80) . "\n";
$campaigns = $conn->query("SELECT campaign_id, description, mail_subject FROM campaign_master ORDER BY campaign_id DESC LIMIT 5");
if ($campaigns && $campaigns->num_rows > 0) {
    while ($row = $campaigns->fetch_assoc()) {
        echo "  Campaign #{$row['campaign_id']}: {$row['description']}\n";
        echo "    └─ Subject: {$row['mail_subject']}\n";
    }
} else {
    echo "  No campaigns found.\n";
}

// Check campaign_status
echo "\n" . str_repeat('=', 80) . "\n";
echo "Campaign Status:\n";
echo str_repeat('-', 80) . "\n";
$status = $conn->query("SELECT cs.campaign_id, cs.status, cs.total_emails, cs.sent_emails, 
                               cs.failed_emails, cs.pending_emails, cm.description
                        FROM campaign_status cs
                        LEFT JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
                        ORDER BY cs.campaign_id DESC LIMIT 5");
if ($status && $status->num_rows > 0) {
    while ($row = $status->fetch_assoc()) {
        echo "  Campaign #{$row['campaign_id']}: {$row['description']}\n";
        echo "    └─ Status: {$row['status']} | Total: {$row['total_emails']} | ";
        echo "Sent: {$row['sent_emails']} | Failed: {$row['failed_emails']} | Pending: {$row['pending_emails']}\n";
    }
} else {
    echo "  No campaign status records found.\n";
}

// Check SMTP configuration
echo "\n" . str_repeat('=', 80) . "\n";
echo "SMTP Configuration:\n";
echo str_repeat('-', 80) . "\n";

// SMTP Servers
$smtp_servers = $conn->query("SELECT id, name, host, port, encryption, is_active FROM smtp_servers");
if ($smtp_servers && $smtp_servers->num_rows > 0) {
    echo "SMTP Servers:\n";
    while ($row = $smtp_servers->fetch_assoc()) {
        $status = $row['is_active'] ? '✓ ACTIVE' : '✗ INACTIVE';
        echo "  [{$status}] Server #{$row['id']}: {$row['name']}\n";
        echo "    └─ {$row['host']}:{$row['port']} ({$row['encryption']})\n";
    }
} else {
    echo "  ⚠ No SMTP servers configured!\n";
}

// SMTP Accounts
echo "\n";
$smtp_accounts = $conn->query("SELECT sa.id, sa.email, sa.is_active, sa.sent_today, sa.total_sent, 
                                      sa.daily_limit, ss.name as server_name
                               FROM smtp_accounts sa
                               LEFT JOIN smtp_servers ss ON sa.smtp_server_id = ss.id
                               ORDER BY sa.id DESC LIMIT 10");
if ($smtp_accounts && $smtp_accounts->num_rows > 0) {
    echo "SMTP Accounts (showing last 10):\n";
    while ($row = $smtp_accounts->fetch_assoc()) {
        $status = $row['is_active'] ? '✓' : '✗';
        $usage = ($row['daily_limit'] > 0) ? round(($row['sent_today'] / $row['daily_limit']) * 100, 1) : 0;
        echo "  [{$status}] Account #{$row['id']}: {$row['email']}\n";
        echo "    └─ Server: {$row['server_name']} | Today: {$row['sent_today']}/{$row['daily_limit']} ({$usage}%) | Total: " . number_format($row['total_sent']) . "\n";
    }
    
    // Summary
    $summary = $conn->query("SELECT COUNT(*) as total, 
                                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                                    SUM(sent_today) as total_sent_today,
                                    SUM(total_sent) as total_sent_all
                             FROM smtp_accounts");
    if ($summary) {
        $sum = $summary->fetch_assoc();
        echo "\n  Summary: {$sum['active']}/{$sum['total']} active accounts | ";
        echo "Sent today: " . number_format($sum['total_sent_today']) . " | ";
        echo "Total sent: " . number_format($sum['total_sent_all']) . "\n";
    }
} else {
    echo "  ⚠ No SMTP accounts configured!\n";
}

// Check valid emails
echo "\n" . str_repeat('=', 80) . "\n";
echo "Email Database:\n";
echo str_repeat('-', 80) . "\n";
$emails = $conn->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN domain_status = 1 AND domain_processed = 1 THEN 1 ELSE 0 END) as valid,
                            SUM(CASE WHEN domain_status = 0 THEN 1 ELSE 0 END) as invalid
                        FROM emails");
if ($emails) {
    $email_data = $emails->fetch_assoc();
    echo "  Total emails: " . number_format($email_data['total']) . "\n";
    echo "  Valid (ready for campaigns): " . number_format($email_data['valid']) . "\n";
    echo "  Invalid: " . number_format($email_data['invalid']) . "\n";
}

// Check mail_blaster queue
echo "\n" . str_repeat('=', 80) . "\n";
echo "Mail Blaster Queue:\n";
echo str_repeat('-', 80) . "\n";
$blaster = $conn->query("SELECT status, COUNT(*) as count FROM mail_blaster GROUP BY status");
if ($blaster && $blaster->num_rows > 0) {
    while ($row = $blaster->fetch_assoc()) {
        echo "  {$row['status']}: " . number_format($row['count']) . "\n";
    }
} else {
    echo "  Queue is empty.\n";
}

// Check for pending/running campaigns
echo "\n" . str_repeat('=', 80) . "\n";
echo "Active Campaigns:\n";
echo str_repeat('-', 80) . "\n";
$active = $conn->query("SELECT cs.campaign_id, cm.description, cs.status, cs.sent_emails, cs.total_emails
                        FROM campaign_status cs
                        LEFT JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
                        WHERE cs.status IN ('running', 'pending')
                        ORDER BY cs.campaign_id DESC");
if ($active && $active->num_rows > 0) {
    while ($row = $active->fetch_assoc()) {
        $progress = ($row['total_emails'] > 0) ? round(($row['sent_emails'] / $row['total_emails']) * 100, 1) : 0;
        echo "  Campaign #{$row['campaign_id']}: {$row['description']}\n";
        echo "    └─ Status: {$row['status']} | Progress: {$progress}% ({$row['sent_emails']}/{$row['total_emails']})\n";
    }
} else {
    echo "  No active campaigns.\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Database verification complete!\n";
echo "============================================\n";

// System readiness check
echo "\n📊 SYSTEM READINESS:\n";
$issues = [];

$smtp_check = $conn->query("SELECT COUNT(*) as count FROM smtp_servers WHERE is_active = 1");
$smtp_count = $smtp_check ? $smtp_check->fetch_assoc()['count'] : 0;
if ($smtp_count == 0) {
    $issues[] = "No active SMTP servers configured";
} else {
    echo "  ✓ SMTP Servers: $smtp_count active\n";
}

$accounts_check = $conn->query("SELECT COUNT(*) as count FROM smtp_accounts WHERE is_active = 1");
$accounts_count = $accounts_check ? $accounts_check->fetch_assoc()['count'] : 0;
if ($accounts_count == 0) {
    $issues[] = "No active SMTP accounts configured";
} else {
    echo "  ✓ SMTP Accounts: $accounts_count active\n";
}

$emails_check = $conn->query("SELECT COUNT(*) as count FROM emails WHERE domain_status = 1 AND domain_processed = 1");
$emails_count = $emails_check ? $emails_check->fetch_assoc()['count'] : 0;
if ($emails_count == 0) {
    $issues[] = "No valid emails in database";
} else {
    echo "  ✓ Valid Emails: " . number_format($emails_count) . "\n";
}

$campaigns_check = $conn->query("SELECT COUNT(*) as count FROM campaign_master");
$campaigns_count = $campaigns_check ? $campaigns_check->fetch_assoc()['count'] : 0;
if ($campaigns_count == 0) {
    $issues[] = "No campaigns created";
} else {
    echo "  ✓ Campaigns Created: $campaigns_count\n";
}

if (!empty($issues)) {
    echo "\n⚠ WARNINGS:\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
}

if (empty($issues) && empty($missing_tables)) {
    echo "\n✅ System is ready for email campaigns!\n";
} else {
    echo "\n⚠ System needs configuration before starting campaigns.\n";
}

$conn->close();
echo "\n";
