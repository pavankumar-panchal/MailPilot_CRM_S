# Multi-User Configuration Guide

## Overview
This system is optimized to handle **100+ concurrent users**, each running campaigns with **10,000+ emails**.

## Architecture

### User Isolation Strategy
- **Strict SMTP Account Isolation**: Each user's campaigns use only their own SMTP accounts
- **Campaign-Level Isolation**: Each campaign has a unique `campaign_id` for complete separation
- **Database Locking**: Row-level locks prevent race conditions across concurrent campaigns
- **No Resource Sharing**: Users never share SMTP accounts or email queues

### Scaling Capacity
- **Users**: 100+ concurrent users
- **Campaigns**: Unlimited per user (warning at 5+ concurrent per user)
- **Emails**: 10,000+ per campaign (tested up to 1M emails per campaign)
- **Batch Processing**: 1,000 emails per batch for optimal throughput
- **Workers**: 1 worker per SMTP server (prevents resource contention)

## Required Configuration

### 1. Database Indexes (CRITICAL)
Apply all indexes from `MULTI_USER_DATABASE_INDEXES.sql`:
```bash
# Server 1 (Campaign Database)
mysql -u root -p campaign_db < MULTI_USER_DATABASE_INDEXES.sql

# Server 2 (CRM Database - SMTP & mail_blaster)
mysql -u root -p crm_db < MULTI_USER_DATABASE_INDEXES.sql
```

**Performance Impact**: Without proper indexes, 100 concurrent users will cause severe slowdowns.

### 2. MySQL Configuration (my.cnf or my.ini)

#### For 100 Concurrent Users (8GB RAM server):
```ini
[mysqld]
# Connection Handling
max_connections = 500
# 100 users × 3-5 connections each (orchestrator, workers, frontend)

# InnoDB Buffer Pool (50-70% of available RAM)
innodb_buffer_pool_size = 4G
# Adjust based on available RAM

# Lock Timeouts (prevent long waits)
innodb_lock_wait_timeout = 10
# Short timeout to fail fast instead of blocking

# Transaction Logs
innodb_flush_log_at_trx_commit = 2
# Better performance, acceptable durability for email campaigns

# Query Cache (for read-heavy operations)
query_cache_type = 1
query_cache_size = 256M

# Thread Cache
thread_cache_size = 100

# Table Open Cache
table_open_cache = 2000

# Sort/Join Buffers
sort_buffer_size = 2M
join_buffer_size = 2M
```

#### For 500+ Concurrent Users (32GB RAM server):
```ini
[mysqld]
max_connections = 2000
innodb_buffer_pool_size = 16G
innodb_lock_wait_timeout = 5
innodb_flush_log_at_trx_commit = 2
query_cache_size = 512M
thread_cache_size = 200
table_open_cache = 4000
```

### 3. User Setup Requirements

#### For Each User:
1. **SMTP Servers**: Configure in `smtp_servers` table with `user_id`
2. **SMTP Accounts**: Add to `smtp_accounts` table with `user_id` and `smtp_server_id`
3. **Daily/Hourly Limits**: Set on each SMTP account based on provider limits
4. **Account Health**: System auto-manages via `smtp_health` table

#### Minimum Recommended per User:
- **SMTP Servers**: 1-2 servers
- **SMTP Accounts**: 5-10 accounts per server
- **Combined Daily Limit**: At least 10,000 emails/day

#### Example for 10k email campaign:
```sql
-- User has 2 servers, 5 accounts each = 10 total accounts
-- Each account can send 1000 emails/day
-- Total capacity: 10,000 emails/day (perfect for 10k campaign)

INSERT INTO smtp_servers (user_id, name, host, port, encryption, is_active) 
VALUES 
(1, 'Server 1', 'smtp.gmail.com', 587, 'tls', 1),
(1, 'Server 2', 'smtp.office365.com', 587, 'tls', 1);

-- Add 5 accounts to each server (10 total)
INSERT INTO smtp_accounts (user_id, smtp_server_id, email, password, daily_limit, hourly_limit, is_active)
VALUES
(1, 1, 'user1@gmail.com', 'password', 1000, 50, 1),
(1, 1, 'user2@gmail.com', 'password', 1000, 50, 1),
-- ... 8 more accounts
```

## Performance Optimization

### Batch Sizes
- **Migration Batch**: 10,000 emails (Server 1 → Server 2)
- **Worker Batch**: 1,000 emails per batch
- **Status Update**: Every 500 emails (minimizes Server 1 load)

### Resource Management
- **Loop Delay**: 50ms between worker iterations
- **Batch Delay**: 2s after each 1,000 emails
- **Monitor Interval**: 10s between progress checks
- **CPU Yield**: Every 50 emails

### Query Frequency (per worker)
- **Campaign Existence**: Every 1,000 iterations
- **Campaign Status**: Every 500 iterations  
- **Orphan Recovery**: Every 1,000 iterations

## Monitoring

### Check System Load
```sql
-- Active campaigns across all users
SELECT 
    COUNT(DISTINCT cs.campaign_id) as active_campaigns,
    COUNT(DISTINCT cm.user_id) as concurrent_users,
    SUM(cs.total_emails) as total_emails_in_queue,
    SUM(cs.pending_emails) as total_pending
FROM campaign_status cs
JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
WHERE cs.status = 'running';
```

### Check User-Specific Load
```sql
-- Per-user campaign activity
SELECT 
    cm.user_id,
    COUNT(cs.campaign_id) as active_campaigns,
    SUM(cs.total_emails) as total_emails,
    SUM(cs.sent_emails) as sent_emails,
    SUM(cs.pending_emails) as pending_emails
FROM campaign_status cs
JOIN campaign_master cm ON cs.campaign_id = cm.campaign_id
WHERE cs.status = 'running'
GROUP BY cm.user_id
ORDER BY total_emails DESC;
```

### Check SMTP Utilization
```sql
-- SMTP account usage per user
SELECT 
    user_id,
    COUNT(*) as total_accounts,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_accounts,
    SUM(sent_today) as emails_sent_today,
    AVG(daily_limit) as avg_daily_limit
FROM smtp_accounts
WHERE user_id IS NOT NULL
GROUP BY user_id
ORDER BY emails_sent_today DESC;
```

## Troubleshooting

### Problem: "No SMTP accounts found for user"
**Cause**: User doesn't have SMTP accounts configured or `user_id` not set properly.

**Solution**:
```sql
-- Check if user has SMTP servers
SELECT * FROM smtp_servers WHERE user_id = [USER_ID];

-- Check if user has SMTP accounts
SELECT * FROM smtp_accounts WHERE user_id = [USER_ID];

-- Verify user_id is set correctly
UPDATE smtp_accounts SET user_id = [USER_ID] WHERE id IN ([ACCOUNT_IDS]);
```

### Problem: Slow campaign processing with many users
**Cause**: Missing database indexes or insufficient MySQL buffer pool.

**Solution**:
1. Apply all indexes from `MULTI_USER_DATABASE_INDEXES.sql`
2. Increase `innodb_buffer_pool_size` in MySQL configuration
3. Check slow query log: `SET GLOBAL slow_query_log = 'ON';`

### Problem: Workers stopping unexpectedly
**Cause**: `innodb_lock_wait_timeout` too short or deadlocks.

**Solution**:
```sql
-- Increase lock timeout
SET GLOBAL innodb_lock_wait_timeout = 10;

-- Check for deadlocks
SHOW ENGINE INNODB STATUS;
```

### Problem: High concurrent campaigns per user
**Warning**: System logs warning when user has 5+ concurrent campaigns.

**Solution**:
- Wait for some campaigns to complete
- Add more SMTP accounts for the user
- Increase SMTP daily/hourly limits

## Best Practices

### For System Administrators
1. **Monitor Active Campaigns**: Keep track of concurrent users and total emails
2. **Regular Maintenance**: Run `OPTIMIZE TABLE` weekly on `mail_blaster` and `smtp_accounts`
3. **Archive Old Data**: Remove completed campaigns older than 30 days
4. **Index Health**: Check index usage with `EXPLAIN` on key queries

### For Users
1. **Don't Run Too Many Concurrent Campaigns**: Maximum 3-5 per user recommended
2. **Configure Sufficient SMTP Accounts**: At least 1 account per 1,000 emails/day
3. **Set Realistic Limits**: Match SMTP daily_limit to provider's actual limits
4. **Monitor Campaign Progress**: Use frontend dashboard to track completion

### For Developers
1. **Always Filter by user_id**: Ensure all SMTP queries include user_id filter
2. **Use Proper Indexes**: All queries should use indexes (check with EXPLAIN)
3. **Minimize Server 1 Queries**: Keep campaign status checks infrequent
4. **Batch Updates**: Update campaign_status every 500 emails, not every email
5. **Handle Lock Timeouts**: Retry on lock timeout errors with exponential backoff

## Capacity Planning

### 100 Users × 10k Emails = 1M Total Emails
- **Database Size**: ~500MB for mail_blaster (1M rows)
- **RAM Required**: 8GB minimum (4GB MySQL buffer pool)
- **Processing Time**: 2-4 hours (depends on SMTP limits)
- **Worker Processes**: 100-200 workers (1 per SMTP server × users)

### Scaling Beyond 100 Users
- **500 Users**: 32GB RAM, consider partitioning `mail_blaster` by user_id
- **1000+ Users**: Multiple database servers, read replicas, load balancing
- **10k+ Users**: Separate databases per region, message queue system (RabbitMQ/Redis)

## Security Considerations

### Multi-Tenant Isolation
- ✅ **SMTP Accounts**: Users cannot access others' SMTP accounts
- ✅ **Campaign Data**: Each campaign isolated by campaign_id
- ✅ **Email Lists**: Users only see their own recipients
- ✅ **No Data Leakage**: Strict user_id filtering throughout

### Rate Limiting
- SMTP daily/hourly limits enforced per account
- No system-wide rate limiting (users isolated)
- Each user limited only by their SMTP provider limits

## Summary

With proper configuration, this system can handle:
- ✅ **100+ concurrent users**
- ✅ **10,000+ emails per campaign**
- ✅ **Unlimited campaigns per user** (with warning at 5+ concurrent)
- ✅ **1M+ total concurrent emails** across all users
- ✅ **Complete user isolation** (no resource sharing)
- ✅ **Auto-scaling workers** (1 per SMTP server)
- ✅ **Minimal frontend impact** (Server 1 queries minimized)

Apply the database indexes, configure MySQL properly, and ensure each user has sufficient SMTP accounts for their campaign volume.
