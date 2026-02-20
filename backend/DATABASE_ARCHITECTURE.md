# Database Architecture Documentation

## Two-Database System Design

This system uses a **two-database architecture** to optimize performance and separate concerns:

### Server 1 Database: `email_id`
**Purpose**: Master data storage and campaign management  
**Location**: IP `174.141.233.174` (payrollsoft.in)

**Tables**:
- `bounced_emails` - Email bounce tracking
- `campaign_distribution` - Campaign SMTP distribution config
- **`campaign_master`** - Campaign definitions (subject, body, templates)
- **`campaign_status`** - Campaign execution status and statistics
- `csv_list` - CSV file upload metadata
- **`emails`** - Email validation results (CSV source)
- `email_processing_logs` - Processing audit logs
- `exclude_accounts` - Accounts to exclude from campaigns
- `exclude_domains` - Domains to exclude from campaigns
- **`imported_recipients`** - Excel import data (invoice/customer data)
- `mail_templates` - Email templates with merge fields
- `processed_emails` - Received email tracking
- `stats_cache` - Statistics cache
- `unsubscribers` - Unsubscribe tracking
- **`users`** - User accounts
- `user_tokens` - Authentication tokens
- `workers` - Worker process tracking

### Server 2 Database: `CRM`
**Purpose**: Email queue processing and SMTP operations  
**Location**: IP `207.244.80.245` (relyonmail.xyz) OR `localhost:3306` (when running on Server 2)

**Tables**:
- **`mail_blaster`** - Email sending queue (campaign_id, to_mail, status, attempt_count)
- **`smtp_accounts`** - SMTP account credentials and limits
- `smtp_health` - SMTP account health tracking
- `smtp_rotation` - Round-robin SMTP rotation state
- `smtp_servers` - SMTP server configurations
- `smtp_usage` - Hourly/daily usage tracking

---

## Data Flow

### Campaign Creation (Server 1)
1. User creates campaign → `campaign_master` (Server 1)
2. System creates status record → `campaign_status` (Server 1)
3. Recipients loaded from:
   - CSV: `emails` table (Server 1)
   - Excel: `imported_recipients` table (Server 1)

### Campaign Execution (Both Servers)
1. **Orchestrator** (`email_blast_parallel.php`):
   - Reads campaign from `campaign_master` (Server 1)
   - Bulk migrates recipients → `mail_blaster` (Server 2)
   - Updates `campaign_status` (Server 1)
   - Loads SMTP servers/accounts (Server 2)
   - Launches workers (one per SMTP server)

2. **Workers** (`email_blast_worker.php`):
   - Claims emails from `mail_blaster` (Server 2)
   - Fetches recipient data from `imported_recipients` or `emails` (Server 1) - for merge fields only
   - Sends emails via SMTP accounts (Server 2)
   - Updates `mail_blaster` status (Server 2)
   - Updates `smtp_usage` (Server 2)
   - Batch updates `campaign_status` every 500 emails (Server 1)

### Key Principles
- ✅ **Server 1**: Read-only during execution (except status updates)
- ✅ **Server 2**: All heavy write operations (mail_blaster updates)
- ✅ **Batch Processing**: 1000 emails per batch
- ✅ **Minimal Server 1 Queries**: Status updates every 500 emails
- ✅ **User Isolation**: All tables support `user_id` filtering

---

## Database Connection Usage

### In `email_blast_parallel.php` (Orchestrator)
```php
$conn        // Server 1 (email_id) - campaign_master, campaign_status, users
$conn_heavy  // Server 2 (CRM) - mail_blaster, smtp_servers, smtp_accounts
```

### In `email_blast_worker.php` (Workers)
```php
$conn        // Server 1 (email_id) - campaign_master, imported_recipients (merge data)
$conn_heavy  // Server 2 (CRM) - mail_blaster, smtp_accounts, smtp_usage
```

---

## Performance Optimizations

### Batch Processing
- **BATCH_SIZE**: 1000 emails per batch
- **STATUS_UPDATE_INTERVAL**: Update Server 1 every 500 emails
- **BATCH_DELAY**: 2 seconds between batches

### Database Query Optimization
- **Server 1**: Minimal queries (campaign definition, status tracking)
- **Server 2**: All email queue operations (high-volume writes)
- **Incremental Updates**: No COUNT(*) queries during execution
- **Connection Pooling**: Persistent connections with ping-based reconnection

### Multi-User Support
- **User Isolation**: SMTP accounts filtered by `user_id`
- **Campaign Isolation**: Each campaign has unique `campaign_id`
- **Lock Timeouts**: Short locks (1-3 seconds) to prevent frontend blocking

---

## Critical SQL Differences

### Server 1 (`email_id`) - MariaDB 5.5.68
- Collation: `utf8mb4_unicode_ci`
- Status ENUM: `('pending','success','failed')` (3 values)
- Auto-increment: Standard

### Server 2 (`CRM`) - MariaDB 10.3.39
- Collation: `utf8mb4_general_ci` (mail_blaster), `utf8mb4_unicode_ci` (SMTP tables)
- Status ENUM: `('pending','success','failed','processing')` (4 values - includes 'processing')
- Auto-increment: Standard
- Uses `current_timestamp()` instead of `CURRENT_TIMESTAMP`

### Important Notes
- `mail_blaster.status` supports **'processing'** on Server 2 (used to prevent race conditions)
- Collation differences require `COLLATE` in JOIN queries between servers
- Server 2 is newer MariaDB (10.3 vs 5.5), supports better performance features

---

## Troubleshooting

### If campaigns don't start:
1. Check `campaign_status.status` (Server 1) - should be 'running'
2. Check `smtp_accounts` (Server 2) - ensure user has active accounts
3. Check `mail_blaster` (Server 2) - verify records were migrated

### If emails aren't sending:
1. Check `mail_blaster` status distribution (Server 2)
2. Check `smtp_health` (Server 2) - ensure accounts are 'healthy'
3. Check `smtp_usage` (Server 2) - verify not hitting limits

### If campaign stuck:
1. Check worker processes: `ps aux | grep email_blast_worker`
2. Check orchestrator: `ps aux | grep email_blast_parallel`
3. Check logs: `backend/logs/` directory
4. Reset 'processing' status: 
   ```sql
   UPDATE mail_blaster SET status='pending' 
   WHERE campaign_id=X AND status='processing' 
   AND delivery_time < DATE_SUB(NOW(), INTERVAL 60 SECOND);
   ```

---

## Security Considerations

### User Isolation
- All queries include `user_id` filtering where applicable
- SMTP accounts are strictly user-specific
- Campaign access controlled by `user_id`

### Database Access
- Server 1: Credentials in `backend/config/db.php`
- Server 2: Credentials in `backend/config/db_campaign.php`
- Passwords: Use strong passwords (already configured)

### Performance Limits
- Daily limit: Configured per SMTP account
- Hourly limit: Configured per SMTP account
- Concurrent campaigns: Limited by available SMTP accounts

---

Generated: 2026-02-20
Version: 2.0 (Two-database architecture)
