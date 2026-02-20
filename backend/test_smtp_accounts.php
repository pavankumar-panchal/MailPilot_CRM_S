#!/usr/bin/env php
<?php
/**
 * Diagnostic script to verify SMTP accounts on Server 2
 * and test email sending capability
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_campaign.php';

echo "\n=== DUAL DATABASE DIAGNOSTICS ===\n\n";

// Test Server 1 connection
echo "✓ Server 1 (email_id): Connected to {$conn->host_info}\n";
echo "  Database: email_id\n";

// Test Server 2 connection
echo "✓ Server 2 (CRM): Connected to {$conn_heavy->host_info}\n";
echo "  Database: CRM\n\n";

// Check SMTP servers on Server 2
echo "=== SMTP SERVERS (Server 2) ===\n";
$serversResult = $conn_heavy->query("SELECT * FROM smtp_servers WHERE is_active = 1");
if ($serversResult && $serversResult->num_rows > 0) {
    while ($server = $serversResult->fetch_assoc()) {
        echo "\nServer #{$server['id']}: {$server['host']}:{$server['port']}\n";
        echo "  Encryption: {$server['encryption']}\n";
        
        // Check accounts for this server
        echo "  SMTP Accounts:\n";
        $accountsResult = $conn_heavy->query("
            SELECT sa.*, u.email as owner_email 
            FROM smtp_accounts sa 
            LEFT JOIN users u ON sa.user_id = u.id
            WHERE sa.smtp_server_id = {$server['id']} AND sa.is_active = 1
        ");
        
        if ($accountsResult && $accountsResult->num_rows > 0) {
            while ($account = $accountsResult->fetch_assoc()) {
                $user_info = $account['owner_email'] ? " (Owner: {$account['owner_email']})" : " (No owner)";
                echo "    └─ Account #{$account['id']}: {$account['email']}$user_info\n";
                echo "       Daily: {$account['sent_today']}/{$account['daily_limit']}\n";
                echo "       Hourly: Check smtp_usage table\n";
                echo "       User ID: " . ($account['user_id'] ?: 'NULL - ERROR!') . "\n";
            }
        } else {
            echo "    └─ NO ACTIVE ACCOUNTS FOUND!\n";
        }
    }
} else {
    echo "❌ NO ACTIVE SMTP SERVERS FOUND!\n";
}

// Check for campaigns
echo "\n\n=== ACTIVE CAMPAIGNS (Server 1) ===\n";
$campaignsResult = $conn->query("
    SELECT cm.campaign_id, cm.user_id, cm.mail_subject, cs.status, cs.total_emails
    FROM campaign_master cm
    LEFT JOIN campaign_status cs ON cm.campaign_id = cs.campaign_id
    WHERE cs.status IN ('running', 'pending')
    LIMIT 5
");

if ($campaignsResult && $campaignsResult->num_rows > 0) {
    while ($campaign = $campaignsResult->fetch_assoc()) {
        echo "\nCampaign #{$campaign['campaign_id']}: {$campaign['mail_subject']}\n";
        echo "  User ID: {$campaign['user_id']}\n";
        echo "  Status: {$campaign['status']}\n";
        echo "  Total Emails: {$campaign['total_emails']}\n";
        
        // Check mail_blaster records on Server 2
        $blasterResult = $conn_heavy->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM mail_blaster 
            WHERE campaign_id = {$campaign['campaign_id']}
        ");
        
        if ($blasterResult && $blasterResult->num_rows > 0) {
            $stats = $blasterResult->fetch_assoc();
            echo "  Mail Blaster (Server 2):\n";
            echo "    Total: {$stats['total']}\n";
            echo "    Success: {$stats['success']}\n";
            echo "    Pending: {$stats['pending']}\n";
            echo "    Processing: {$stats['processing']}\n";
            echo "    Failed: {$stats['failed']}\n";
            
            // Check stuck processing records
            $stuckResult = $conn_heavy->query("
                SELECT COUNT(*) as stuck
                FROM mail_blaster 
                WHERE campaign_id = {$campaign['campaign_id']}
                AND status = 'processing'
                AND delivery_time < DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ");
            if ($stuckResult) {
                $stuck = $stuckResult->fetch_assoc();
                if ($stuck['stuck'] > 0) {
                    echo "    ⚠️  STUCK PROCESSING: {$stuck['stuck']} (timeout > 60s)\n";
                }
            }
        }
    }
} else {
    echo "No running campaigns found.\n";
}

// Check user SMTP account mapping
echo "\n\n=== USER → SMTP ACCOUNT VERIFICATION ===\n";
$userAccountsResult = $conn_heavy->query("
    SELECT u.id as user_id, u.email as user_email, COUNT(sa.id) as smtp_count
    FROM users u
    LEFT JOIN smtp_accounts sa ON u.id = sa.user_id AND sa.is_active = 1
    GROUP BY u.id
    HAVING smtp_count > 0
    LIMIT 10
");

if ($userAccountsResult && $userAccountsResult->num_rows > 0) {
    while ($user = $userAccountsResult->fetch_assoc()) {
        echo "User #{$user['user_id']} ({$user['user_email']}): {$user['smtp_count']} SMTP accounts\n";
    }
} else {
    echo "❌ NO USERS WITH SMTP ACCOUNTS FOUND!\n";
}

// Check for SMTP accounts without user_id
echo "\n=== ORPHANED SMTP ACCOUNTS (No user_id) ===\n";
$orphanedResult = $conn_heavy->query("
    SELECT id, email, smtp_server_id 
    FROM smtp_accounts 
    WHERE (user_id IS NULL OR user_id = 0) AND is_active = 1
");

if ($orphanedResult && $orphanedResult->num_rows > 0) {
    echo "❌ Found orphaned SMTP accounts (these will NOT be used by worker):\n";
    while ($orphan = $orphanedResult->fetch_assoc()) {
        echo "  Account #{$orphan['id']}: {$orphan['email']} (Server: {$orphan['smtp_server_id']})\n";
    }
    echo "\n⚠️  ACTION REQUIRED: Update these accounts with correct user_id!\n";
} else {
    echo "✓ All SMTP accounts have valid user_id\n";
}

echo "\n=== DIAGNOSTICS COMPLETE ===\n\n";

$conn->close();
$conn_heavy->close();
?>
