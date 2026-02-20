# Campaign System - Deployment Guide

## Files Modified/Created

### ✅ Modified Files (deploy these to production)

1. **backend/config/db_campaign.php**
   - Added 5-second connection timeout (prevents 60s hangs)
   - Added 3-attempt retry logic with exponential backoff
   - Added read/write timeouts (10s each)
   - Optimized session settings for high-traffic scenarios

2. **email_blast_parallel (1).php**
   - Optimized monitoring loop (15-20s adaptive intervals vs 5s)
   - Added prepared statement caching
   - Adaptive check intervals based on progress
   - Reduced Server 1 load by 66-75%

### ✅ New Files (upload these to production)

3. **backend/includes/connection_pool.php** (NEW)
   - Connection pooling (max 20 concurrent)
   - Connection health monitoring
   - Automatic retry and cleanup
   - Prevents connection exhaustion

4. **backend/scripts/campaign_health_check.php** (NEW)
   - System health verification script
   - Tests all optimizations
   - Monitors connection performance

5. **CAMPAIGN_OPTIMIZATION_REPORT.md** (NEW)
   - Complete optimization documentation
   - Performance benchmarks
   - Troubleshooting guide

---

## Deployment Steps

### Step 1: Backup Current Files
```bash
# On production server
cd /var/www/vhosts/payrollsoft.in/httpdocs
cp backend/config/db_campaign.php backend/config/db_campaign.php.backup
cp email_blast_parallel\ \(1\).php email_blast_parallel\ \(1\).php.backup
```

### Step 2: Upload Modified Files

Upload these files from your dev environment to production:
1. `backend/config/db_campaign.php`
2. `email_blast_parallel (1).php`
3. `backend/includes/connection_pool.php` (new)
4. `backend/scripts/campaign_health_check.php` (new)

### Step 3: Verify File Permissions
```bash
cd /var/www/vhosts/payrollsoft.in/httpdocs
chmod 644 backend/config/db_campaign.php
chmod 644 email_blast_parallel\ \(1\).php
chmod 644 backend/includes/connection_pool.php
chmod 755 backend/scripts/campaign_health_check.php
```

### Step 4: Test on Production
```bash
cd /var/www/vhosts/payrollsoft.in/httpdocs
php backend/scripts/campaign_health_check.php
```

**Expected output**:
```
✅ Server 1 (email_id) connected in 0.0Xs
✅ Server 2 (CRM) connected in 0.Xs
✅ Connection pool working
✅ Cache system working
✅ All systems operational
```

### Step 5: Restart Campaign Workers (if any running)
```bash
# Kill existing workers
killall -9 php

# Or more safely, let them finish current batch
ps aux | grep email_blast | grep -v grep | awk '{print $2}' | xargs kill -15
```

### Step 6: Monitor for 24 Hours

Watch for these improvements:
- Frontend APIs no longer timing out
- Campaign progress updates showing in dashboard
- Faster response times on campaigns_master endpoint
- No connection timeout errors in PHP error logs

### Step 7: Check Logs
```bash
# Check for connection errors
tail -f /var/www/vhosts/payrollsoft.in/httpdocs/backend/logs/db_error.log

# Check campaign cron
tail -f /var/www/vhosts/payrollsoft.in/httpdocs/backend/logs/campaign_cron.log

# Check PHP error log
tail -f /var/log/php_errors.log  # or wherever your PHP errors go
```

---

## Key Improvements

### Before
```
Connection timeout: 60 seconds (blocked frontend for 60s)
Monitoring queries: Every 5 seconds (12/min → high Server 1 load)
Connection retry: None (fail immediately)
Connection pooling: None (exhaustion possible)
```

### After
```
Connection timeout: 5 seconds (fast fail & retry)
Monitoring queries: Every 15-20 seconds (3-4/min → 66-75% reduction)
Connection retry: 3 attempts with backoff (higher reliability)
Connection pooling: Max 20 concurrent (prevents exhaustion)
```

---

## Rollback Plan (if needed)

If anything goes wrong:

```bash
cd /var/www/vhosts/payrollsoft.in/httpdocs

# Restore backups
cp backend/config/db_campaign.php.backup backend/config/db_campaign.php
cp email_blast_parallel\ \(1\).php.backup email_blast_parallel\ \(1\).php

# Delete new files
rm backend/includes/connection_pool.php
rm backend/scripts/campaign_health_check.php

# Restart workers
killall -9 php
```

---

## Verification Checklist

After deployment, verify:

- [ ] Health check script runs without errors
- [ ] Server 1 connection timeout < 1s
- [ ] Server 2 connection timeout < 10s (if remote)
- [ ] Frontend APIs respond in < 2 seconds
- [ ] Campaign dashboard shows real-time updates
- [ ] No "Connection timed out" PHP warnings in logs
- [ ] Campaign workers processing emails successfully
- [ ] No frontend blocking during campaign execution

---

## Performance Monitoring

### Check connection pool utilization
```bash
php -r "require 'backend/includes/connection_pool.php'; print_r(ConnectionPool::getStats());"
```

### Check database connections
```bash
# On Server 1
mysql -e "SHOW PROCESSLIST;" | grep -c "email_id"

# On Server 2 (if accessible)
mysql -h 207.244.80.245 -u email_id -p -e "SHOW PROCESSLIST;" | grep -c "CRM"
```

### Check campaign performance
```bash
# Active campaigns
mysql -e "SELECT campaign_id, status, total_emails, sent_emails, pending_emails FROM campaign_status WHERE status='running';"

# Monitoring interval (should be 15-20s)
ps aux | grep email_blast_parallel | grep -v grep
```

---

## Troubleshooting

### Issue: Health check fails with connection errors

**Cause**: Database credentials or network issues

**Fix**:
1. Check `backend/config/db.php` - verify Server 1 credentials
2. Check `backend/config/db_campaign.php` - verify Server 2 credentials
3. Test manual connection:
```bash
mysql -h 127.0.0.1 -u email_id -p email_id
mysql -h 207.244.80.245 -u <user> -p CRM
```

### Issue: Frontend still slow

**Cause**: Connection pool exhausted or too small

**Fix**:
1. Check pool stats: `php -r "require 'backend/includes/connection_pool.php'; print_r(ConnectionPool::getStats());"`
2. Increase max connections in `connection_pool.php`:
```php
private static $maxConnections = 30; // Increase from 20
```

### Issue: Campaigns not progressing

**Cause**: Workers not using optimized connection logic

**Fix**:
1. Check if workers are running: `ps aux | grep email_blast_worker`
2. Check worker logs: `tail -50 logs/email_worker_*.log`
3. Restart campaign: Stop workers, then re-trigger from dashboard

---

## Success Indicators

You'll know the optimizations are working when you see:

✅ **Frontend APIs respond instantly** even during active campaigns  
✅ **No connection timeout errors** in PHP logs  
✅ **Campaign dashboard updates smoothly** every 15-20 seconds  
✅ **Database connections stay under 20** per server  
✅ **Server 1 CPU usage decreased** due to reduced query frequency  
✅ **Workers retry failed connections** automatically (check logs for "attempt 2/3")  

---

## Support

If issues persist after deployment:

1. Run health check: `php backend/scripts/campaign_health_check.php`
2. Check logs in `backend/logs/`
3. Review `CAMPAIGN_OPTIMIZATION_REPORT.md` for detailed troubleshooting
4. Verify network connectivity between Server 1 and Server 2

**Optimization Date**: February 2024  
**Status**: Production-ready  
**Testing**: Health check script included
