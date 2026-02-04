# Email Campaign Performance Analysis

## Configuration Summary

### System Architecture
- **Parallel Workers**: 3 workers per SMTP server (MAX_WORKERS_PER_SERVER = 3)
- **Email Send Delay**: 10ms per email (EMAIL_SEND_DELAY_US = 10000)
- **SMTP Connection**: Persistent connections with reuse (SMTPKeepAlive = true)
- **SMTP Timeout**: 5 seconds for faster failure detection
- **Worker Launch Delay**: 5ms between worker launches
- **No Artificial Delays**: "NO DELAY - Maximum speed with persistent connections" (line 343)

### Rate Limiting
- **Per-Account Hourly Limit**: Configurable (checked via `smtp_usage` table)
- **Per-Account Daily Limit**: Configurable (checked via `smtp_usage` table)
- **Health Monitoring**: Accounts suspended after 15 consecutive failures (30 min suspension)

---

## Performance Calculation: 5000 Emails with 1 Server + 10 SMTP Accounts

### Scenario Details
- **Total Emails**: 5,000
- **SMTP Accounts**: 10 accounts
- **Workers per Server**: 3 parallel workers
- **Server Count**: 1 server

### Key Constraints

#### 1. **Worker Parallelism**
- Maximum of **3 workers** can run simultaneously on 1 server
- Each worker handles **1 SMTP account** at a time
- With 10 accounts available, workers will cycle through accounts based on availability and limits

#### 2. **Email Distribution**
- Workers use **round-robin selection** from available accounts
- Workers skip accounts that have reached hourly/daily limits
- If all accounts hit limits, workers exit and wait

#### 3. **Sending Speed Per Worker**
From the code analysis:
- **Connection Setup**: ~0.1-0.3 seconds (persistent connection reused)
- **Email Composition**: ~0.01-0.02 seconds (template merge, body processing)
- **SMTP Send**: ~0.05-0.15 seconds (actual transmission via PHPMailer)
- **Database Updates**: ~0.01-0.03 seconds (mail_blaster, smtp_usage, smtp_accounts)
- **Total per Email**: ~0.17-0.5 seconds

**Realistic Average**: **~0.25 seconds per email** (4 emails/second per worker)

---

## Time Calculations

### Scenario 1: **No Rate Limits (Unlimited Accounts)**

**Assumptions**:
- All 10 accounts have no hourly/daily limits (or very high limits)
- 3 workers running continuously
- Each worker sends at 4 emails/second

**Calculation**:
```
Total emails = 5,000
Workers = 3
Speed per worker = 4 emails/second
Combined throughput = 3 × 4 = 12 emails/second

Time = 5,000 ÷ 12 = 416.67 seconds
Time = ~7 minutes
```

✅ **Best Case: ~7-8 minutes**

---

### Scenario 2: **With Hourly Rate Limits (Realistic)**

**Assumptions**:
- Each SMTP account has a **100 emails/hour limit** (common for shared SMTP)
- 10 accounts × 100/hour = 1,000 emails/hour total capacity
- 3 workers cycle through accounts

**Calculation**:
```
Total emails = 5,000
Hourly capacity = 10 accounts × 100 emails/hour = 1,000 emails/hour

Time for first 1,000 emails = ~7-8 minutes (unlimited speed)
After 1,000 emails, all accounts hit hourly limit

Remaining emails = 4,000
Must wait for next hour cycle or spread across time

If limits reset hourly:
- Hour 1: 1,000 emails sent in 7-8 minutes
- Hour 2-5: 1,000 emails per hour

Total time = 5 hours + 8 minutes
```

⚠️ **With 100/hour limits: ~5 hours**

**More Generous Limits (500/hour per account)**:
```
Total capacity = 10 × 500 = 5,000 emails/hour
Time = 1 hour (limited by hourly quota, not sending speed)
```

✅ **With 500/hour limits: ~1 hour**

---

### Scenario 3: **With Daily Limits Only**

**Assumptions**:
- Each account has **1,000 emails/day limit** (no hourly limit)
- 10 accounts × 1,000/day = 10,000 emails/day capacity
- Workers run at maximum speed until limits hit

**Calculation**:
```
Total emails = 5,000
Daily capacity = 10,000 emails (sufficient)

Time = 5,000 ÷ 12 emails/second = 416.67 seconds
Time = ~7 minutes
```

✅ **With daily-only limits (≥500/account): ~7-8 minutes**

---

## Real-World Performance Factors

### Bottlenecks
1. **SMTP Server Response Time**
   - Shared SMTP servers: 0.2-0.5 seconds per email
   - Dedicated SMTP servers: 0.05-0.15 seconds per email
   - Network latency adds 10-50ms per email

2. **Database Locking**
   - Row-level locks on `mail_blaster` table during claim
   - Workers use `SELECT ... FOR UPDATE` for atomic claims
   - Minimal contention with only 3 workers

3. **Account Rate Limits**
   - **Most Critical Factor**: Hourly limits are the primary constraint
   - If all 10 accounts hit hourly limits simultaneously, workers pause

4. **Worker Competition**
   - With 3 workers and 10 accounts, minimal competition
   - Each worker claims next available account

### Optimizations in Code
✅ **Persistent SMTP Connections**: Connection reuse eliminates handshake overhead
✅ **No Artificial Delays**: Code explicitly states "NO DELAY" between emails
✅ **Parallel Processing**: 3 workers run simultaneously
✅ **Efficient Account Selection**: Round-robin with limit checking
✅ **Fast Failure Detection**: 5-second SMTP timeout prevents hanging

---

## Summary Table

| Scenario | Hourly Limit/Account | Total Capacity/Hour | Time to Send 5K |
|----------|---------------------|---------------------|-----------------|
| **Best Case (No Limits)** | Unlimited | 43,200 emails/hr<br>(12/sec × 3600) | **7-8 minutes** |
| **Shared SMTP (100/hr)** | 100/hour | 1,000/hour | **~5 hours** |
| **Mid-Tier (250/hr)** | 250/hour | 2,500/hour | **~2 hours** |
| **Premium (500/hr)** | 500/hour | 5,000/hour | **~1 hour** |
| **Daily Only (1000/day)** | No hourly limit | 43,200/hour | **7-8 minutes** |

---

## Recommendations

### For 5K Email Campaign
1. **Check SMTP Account Limits**:
   ```sql
   SELECT email, hourly_limit, daily_limit FROM smtp_accounts WHERE user_id = YOUR_USER_ID;
   ```

2. **Calculate Total Capacity**:
   ```
   Hourly Capacity = SUM(hourly_limit for all 10 accounts)
   If Hourly Capacity < 5,000: Time = 5,000 ÷ Hourly Capacity (in hours)
   If Hourly Capacity ≥ 5,000: Time = ~7-8 minutes (speed-limited, not quota-limited)
   ```

3. **Optimize for Speed**:
   - ✅ Use accounts with **no hourly limits** (or very high limits like 1000/hour)
   - ✅ Ensure all 10 accounts are **healthy** (check `smtp_health` table)
   - ✅ Use **dedicated SMTP servers** (faster than shared)
   - ✅ Keep worker count at 3 (already optimal for 1 server)

4. **Monitor Progress**:
   - Check `smtp_usage` table to see real-time sending rate
   - Check `campaign_master` table for campaign status
   - Check worker logs in `DEBUG_LOG_FILE` for performance metrics

---

## Code References

- **Worker Configuration**: [backend/includes/email_blast_parallel.php](backend/includes/email_blast_parallel.php#L108) (MAX_WORKERS_PER_SERVER = 3)
- **Send Delay Configuration**: [backend/includes/performance_config.php](backend/includes/performance_config.php#L22) (EMAIL_SEND_DELAY_US = 10000)
- **No Delay Comment**: [backend/includes/email_blast_worker.php](backend/includes/email_blast_worker.php#L343) ("NO DELAY - Maximum speed")
- **Persistent Connections**: [backend/includes/email_blast_worker.php](backend/includes/email_blast_worker.php#L703) (SMTPKeepAlive = true)
- **Limit Checking**: [backend/includes/email_blast_worker.php](backend/includes/email_blast_worker.php#L1119-L1142) (accountWithinLimits function)
- **Usage Tracking**: [backend/includes/email_blast_worker.php](backend/includes/email_blast_worker.php#L1044-L1050) (smtp_usage table updates)

---

## Conclusion

**Answer: With 1 server and 10 SMTP accounts:**

- ✅ **If no hourly limits**: **7-8 minutes** (pure speed-limited)
- ⚠️ **If 100 emails/hour limit per account**: **~5 hours** (quota-limited)
- ✅ **If 500 emails/hour limit per account**: **~1 hour** (quota-limited)
- ✅ **If daily limits only (≥500/account)**: **7-8 minutes** (speed-limited)

**The actual time depends entirely on your SMTP account hourly/daily limits, not on the system's sending speed.**
