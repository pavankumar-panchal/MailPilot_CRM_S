# Database Connection Issues - Fixed

## Problems Found

### 1. **Orchestrator Connection Leak** (`email_blast_parallel.php`)
**Before:**
```php
while (true) {
    require_once __DIR__ . '/../config/db.php';  // ❌ Doesn't always create new connection
    // ... work ...
    $conn->close();
    sleep(2);
}
```

**Issue:** `require_once` caches the file, so `$conn` isn't recreated. Old connections accumulate.

**Fixed:**
```php
while (true) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();  // ✅ Close old connection first
    }
    require __DIR__ . '/../config/db.php';  // ✅ Use require (not require_once)
    // ... work ...
    $conn->close();
    sleep(2);
}
```

---

### 2. **Worker Persistent Connections** (`email_blast_worker.php`)
**Before:**
```php
require_once __DIR__ . '/../config/db.php';  // ONE connection at start
while (true) {
    // Hundreds of queries on same connection
    // Connection never refreshed
}
$conn->close();  // Only when exiting
```

**Issue:** 7 workers × 1 persistent connection each = 7 connections held indefinitely. Over time:
- Connections go stale (timeouts)
- Server hits `max_connections` limit
- New requests fail with "Too many connections"

**Fixed:**
```php
require_once __DIR__ . '/../config/db.php';
$loop_iter = 0;
while (true) {
    $loop_iter++;
    
    // Refresh connection every 100 iterations (~5-10 mins)
    if ($loop_iter % 100 === 0) {
        if ($conn && $conn->ping()) {
            // Connection alive, continue
        } else {
            // Reconnect if dead
            $conn->close();
            require __DIR__ . '/../config/db.php';
        }
    }
    // ... work ...
}
$conn->close();
```

---

## Impact

**Before:**
- 7 workers + 1 orchestrator = **8+ permanent connections**
- Stale connections accumulating
- "Too many connections" errors

**After:**
- Orchestrator: Fresh connection every 2 seconds (properly closed)
- Workers: Connection health checked every 100 iterations
- Dead connections automatically reconnected
- Stale connections eliminated

---

## Additional Optimizations

### Connection Pooling (Future Enhancement)
Consider implementing connection pooling to reuse connections efficiently:
```php
// In db.php
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
$conn->options(MYSQLI_OPT_READ_TIMEOUT, 30);
```

### Monitor Connections
Check current connections:
```sql
SHOW PROCESSLIST;
SELECT COUNT(*) as connections FROM information_schema.PROCESSLIST;
```

### MySQL Configuration
Adjust `max_connections` if needed:
```sql
SET GLOBAL max_connections = 200;  -- Adjust based on server capacity
```

---

## Files Changed

1. `/backend/includes/email_blast_parallel.php`
   - Line ~806: Changed `require_once` to `require` with explicit close

2. `/backend/includes/email_blast_worker.php`
   - Line ~127: Added connection refresh every 100 iterations

## Deployment

Copy both files to production:
```bash
scp /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/email_blast_parallel.php \
    root@payrollsoft.in:/var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/includes/

scp /opt/lampp/htdocs/verify_emails/MailPilot_CRM_S/backend/includes/email_blast_worker.php \
    root@payrollsoft.in:/var/www/vhosts/payrollsoft.in/httpdocs/emailvalidation/backend/includes/
```

Then restart campaign or wait for cron to restart workers.
