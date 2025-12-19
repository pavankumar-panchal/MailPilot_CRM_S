# CSV List Selection Feature - Deployment Notes

## Date: December 18, 2025

## ⚠️ CRITICAL - DATABASE MIGRATION REQUIRED

**MUST RUN BEFORE DEPLOYING CODE**

### Step 1: Add csv_list_id Column to mail_blaster Table

```sql
-- Connect to your database first
USE email_id;  -- or your database name

-- Add csv_list_id column to mail_blaster table
ALTER TABLE `mail_blaster` 
ADD COLUMN `csv_list_id` INT(10) UNSIGNED DEFAULT NULL AFTER `to_mail`,
ADD INDEX `idx_mail_blaster_csv_list` (`csv_list_id`),
ADD INDEX `idx_mail_blaster_campaign_csv` (`campaign_id`, `csv_list_id`, `status`);

-- Update existing records with csv_list_id from emails table
UPDATE mail_blaster mb
INNER JOIN emails e ON e.raw_emailid = mb.to_mail
SET mb.csv_list_id = e.csv_list_id
WHERE mb.csv_list_id IS NULL;
```

**SQL Script Location:** `/backend/scripts/add_csv_list_to_mail_blaster.sql`

**Verification:**
```sql
-- Check if column was added
SHOW COLUMNS FROM mail_blaster LIKE 'csv_list_id';

-- Check if data was populated
SELECT COUNT(*) as total, 
       COUNT(csv_list_id) as with_csv_list 
FROM mail_blaster;
```

---

## Feature Overview
Added CSV list selection functionality to campaigns. Users can now select a specific CSV list for each campaign, and the system will **accurately track sent/failed counts per CSV list**.

### Key Improvement
✅ **Previously:** Sent/failed counts would reset to 0 when changing CSV list selection  
✅ **Now:** Counts are stored with each email send and remain accurate for each CSV list

## Files Modified

### Backend Files:
1. **`/backend/includes/campaign.php`**
   - Added support for partial updates (csv_list_id only)
   - Handles NULL values correctly for csv_list_id

2. **`/backend/public/campaigns_master.php`**
   - Updated `getEmailCounts()` function to filter by csv_list_id from mail_blaster table
   - Calculates pending as: total_valid - sent - failed
   - Uses COALESCE to prevent NULL counts

3. **`/backend/includes/start_campaign.php`**
   - Retrieves csv_list_id from campaign
   - Passes csv_list_id to email workers via JSON

4. **`/backend/includes/email_blast_parallel.php`**
   - Updated `getEmailsRemainingCount()` to accept csv_list_id parameter
   - Filters remaining email count by csv_list_id

5. **`/backend/includes/email_blast_worker.php`** ⭐ **MAJOR CHANGES**
   - Extracts csv_list_id from campaign JSON
   - SELECT queries fetch csv_list_id from emails table
   - INSERT into mail_blaster includes csv_list_id column
   - `recordDelivery()` function updated to accept and store csv_list_id
   - `claimNextEmail()` returns csv_list_id with claimed email
   - `fetchNextPending()` returns csv_list_id from mail_blaster
   - `sendEmail()` receives csv_list_id and passes to recordDelivery
   - All email selection queries include csv_list_id filter

### Frontend Files:
1. **`/frontend/src/pages/Master.jsx`**
   - Added searchable CSV list selection modal
   - Real-time count updates based on selected CSV list
   - Immediate save when CSV list is changed
   - Dynamic display of sent/failed/pending counts

2. **`/frontend/src/config.js`**
   - Fixed LOCAL_BASE URL: `MailPilot_CRM_S` (was missing _S suffix)

## Database Schema Changes

### New Column in mail_blaster Table:
```sql
csv_list_id INT(10) UNSIGNED DEFAULT NULL
```

**Purpose:** Tracks which CSV list each email belongs to when it's sent

### New Indexes:
- `idx_mail_blaster_csv_list` - Fast lookups by csv_list_id
- `idx_mail_blaster_campaign_csv` - Optimized for count queries (campaign_id, csv_list_id, status)

## How It Works

### 1. CSV List Selection
- User clicks on campaign row dropdown in Master page
- Selects CSV list from searchable modal
- Selection is immediately saved to database via API

### 2. Email Filtering & Tracking
When campaign starts:
- System retrieves csv_list_id from campaign_master table
- Passes csv_list_id to all 7 parallel workers
- Workers apply filter: `WHERE e.csv_list_id = X`
- When email is claimed/sent, csv_list_id is stored in mail_blaster table
- This preserves the CSV list association permanently

### 3. Count Calculation (NEW LOGIC)
**Total Valid:** Count from emails table filtered by csv_list_id
```sql
SELECT COUNT(*) FROM emails 
WHERE domain_status=1 AND validation_status='valid' 
AND csv_list_id = X
```

**Sent & Failed:** Count from mail_blaster table filtered by csv_list_id
```sql
SELECT 
  SUM(status='success') as sent,
  SUM(status='failed' AND attempt_count>=5) as failed
FROM mail_blaster 
WHERE campaign_id=Y AND csv_list_id=X
```

**Pending:** Calculated as `total_valid - sent - failed`

## Important Notes

### ✅ Count Display Now Works Correctly

**OLD BEHAVIOR (Before this fix):**
```
1. Campaign with csv_list_id = 5
2. Send 1000 emails successfully → Sent: 1000
3. Change csv_list_id to 10
4. Counts show: Sent: 0, Failed: 0  ❌ Wrong!
```

**NEW BEHAVIOR (After this fix):**
```
1. Campaign with csv_list_id = 5
2. Send 1000 emails → mail_blaster stores csv_list_id=5 for each
3. Change campaign csv_list_id to 10
4. Counts for list 5 still show: Sent: 1000 ✅ Correct!
5. Counts for list 10 show: Sent: 0 (no emails sent yet for this list)
6. Switch back to list 5 → Still shows Sent: 1000 ✅ Persisted!
```

### Example Scenarios:

**Scenario A: Send to Multiple Lists**
```
Day 1: Select CSV List "Customers" (ID 5)
       Send 500 emails
       Counts: Sent=500, Failed=10, Pending=490

Day 2: Select CSV List "Prospects" (ID 8)  
       Send 300 emails
       Counts: Sent=300, Failed=5, Pending=195
       
View "Customers" again → Counts: Sent=500, Failed=10 (preserved!)
```

**Scenario B: Re-run Campaign on Same List**
```
Campaign #100 with CSV List ID 5
First run: Sent=1000, Failed=50
Stop campaign, restart later
Second run continues where it left off
Counts accumulate: Sent=1500, Failed=75
```

## Deployment Steps

### 1. ⚠️ Database Migration (REQUIRED FIRST)
```bash
# On production server, run the SQL migration
mysql -u root -p email_id < /path/to/backend/scripts/add_csv_list_to_mail_blaster.sql

# Or manually run the ALTER TABLE commands shown above
```

### 2. Backend Deployment
Upload these files to production server:
```bash
/backend/includes/campaign.php
/backend/public/campaigns_master.php
/backend/includes/start_campaign.php
/backend/includes/email_blast_parallel.php
/backend/includes/email_blast_worker.php  ⭐ CRITICAL FILE
/backend/scripts/add_csv_list_to_mail_blaster.sql
```

### 3. Frontend Deployment
Upload entire `/frontend/dist` folder contents to production:
```bash
rsync -avz --delete /frontend/dist/* user@server:/path/to/production/frontend/
```

### 4. Verify Database
Ensure columns exist:
```sql
-- Check campaign_master.csv_list_id
SHOW COLUMNS FROM campaign_master LIKE 'csv_list_id';

-- Check mail_blaster.csv_list_id (NEW!)
SHOW COLUMNS FROM mail_blaster LIKE 'csv_list_id';
```

### 5. Configuration Check
Production config in `/frontend/src/config.js`:
- **PRODUCTION_BASE:** `https://payrollsoft.in/emailvalidation`
- Automatically detected when not on localhost

## Testing Checklist

- [ ] Run database migration SQL script
- [ ] Verify csv_list_id column exists in mail_blaster table
- [ ] Create new campaign
- [ ] Select CSV list from dropdown
- [ ] Verify selection saves (check database)
- [ ] Start campaign
- [ ] Expand campaign to see counts
- [ ] Verify only emails from selected CSV list are sent
- [ ] Stop campaign after sending some emails
- [ ] Change CSV list selection
- [ ] Verify sent/failed counts show 0 for new list (correct!)
- [ ] Switch back to original CSV list
- [ ] Verify original counts are preserved ✅
- [ ] Test with NULL csv_list_id (should send to all lists)

## API Endpoints Used

- **POST** `/backend/routes/api.php/api/master/campaigns`
  - Action: Update campaign (csv_list_id)
  
- **POST** `/backend/routes/api.php/api/master/campaigns_master`
  - Action: `email_counts` - Get counts for specific campaign and CSV list

## Troubleshooting

### Issue: Counts showing 0 after deployment
**Solution:** Make sure you ran the database migration to add csv_list_id column to mail_blaster

### Issue: Old emails don't have csv_list_id
**Solution:** The UPDATE query in migration script backfills csv_list_id from emails table

### Issue: Counts not updating in real-time
**Solution:** Expand/collapse the campaign row to refresh counts, or wait for auto-refresh interval

## Rollback Plan

If issues occur:

### 1. Revert Code
```bash
git checkout main backend/includes/email_blast_worker.php
git checkout main backend/public/campaigns_master.php
# ... (revert other files)
```

### 2. Remove Database Column (Optional)
```sql
ALTER TABLE mail_blaster DROP COLUMN csv_list_id;
ALTER TABLE mail_blaster DROP INDEX idx_mail_blaster_csv_list;
ALTER TABLE mail_blaster DROP INDEX idx_mail_blaster_campaign_csv;
```

### 3. Rebuild Frontend
```bash
cd frontend && npm run build
```

## Support

For issues or questions:
1. Check browser console for frontend errors
2. Check Apache error log: `/opt/lampp/logs/error_log`
3. Verify API responses in Network tab
4. Check database for csv_list_id values in both tables:
   ```sql
   SELECT campaign_id, csv_list_id FROM campaign_master;
   SELECT id, campaign_id, to_mail, csv_list_id FROM mail_blaster LIMIT 10;
   ```

---

**Build Date:** December 18, 2025  
**Build Status:** ✅ Successful (28.71s)  
**Production Ready:** Yes (after database migration)  
**Database Migration:** ⚠️ **REQUIRED**
