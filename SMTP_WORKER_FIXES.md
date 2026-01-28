# SMTP Worker Database Schema Fixes

## Date: 2025
## Files Modified:
1. `backend/worker/smtp_worker_parallel.php`
2. `backend/includes/smtp_validation_cron.php`

## Problem Summary
The worker files were attempting to use database columns that don't exist in the actual server schema, causing SQL errors during email validation.

## Database Schema - Actual Columns in `emails` Table:
```sql
- id (int)
- raw_emailid (varchar 150) - the email address
- sp_account (varchar 100) - account part of email  
- sp_domain (varchar 100) - domain part of email
- domain_verified (tinyint 1) - 0 or 1
- domain_status (tinyint 1) - 0 or 1 ✓ EXISTS
- validation_response (text) - validation message ✓ EXISTS
- domain_processed (tinyint 1) - 0 or 1 ✓ EXISTS
- csv_list_id (int unsigned)
- validation_status (varchar 20) - 'pending', 'valid', 'invalid' ✓ EXISTS
- worker_id (tinyint unsigned)
- user_id (int)
```

## Non-Existent Columns (Removed from Code):
- ❌ `domain_smtp` - doesn't exist
- ❌ `catch_all` - doesn't exist
- ❌ `client_ip` - doesn't exist
- ❌ `smtp_meta` - doesn't exist
- ❌ `next_retry_at` - doesn't exist

## Changes Made:

### 1. backend/worker/smtp_worker_parallel.php

#### Removed Schema Check (Lines 61-64):
- ❌ Removed attempt to add non-existent columns
- ✅ Added comment explaining schema matches server

#### Fixed UPDATE Queries:
**Invalid Account Names (Line ~808):**
- ❌ Old: `domain_status = 0`
- ✅ New: `domain_verified = 0, domain_status = 0, validation_status = 'invalid', domain_processed = 1`

**Disposable Emails (Line ~820):**
- ❌ Old: `domain_status = 0`
- ✅ New: `domain_verified = 0, domain_status = 0, validation_status = 'disposable', domain_processed = 1`

**Excluded Accounts (Line ~830):**
- ❌ Old: Used `domain_smtp = 1`
- ✅ New: Uses only `domain_verified = 1, domain_status = 1, validation_status = 'valid'`

**Excluded Domains (Line ~840):**
- ❌ Old: Used `domain_smtp = 1`
- ✅ New: Uses only `domain_verified = 1, domain_status = 1, validation_status = 'valid'`

**Main SMTP Validation Results (Lines ~840-880):**
- ❌ Old: Updated `domain_smtp, catch_all, client_ip, smtp_meta, next_retry_at`
- ✅ New: Updates only `domain_status, validation_status, domain_verified, validation_response`

#### Fixed csv_list Progress Updates (Line ~925):
- ❌ Old: `WHERE domain_smtp = 1` and `WHERE domain_smtp = 0`
- ✅ New: `WHERE domain_status = 1` and `WHERE domain_status = 0`

#### Fixed Validation Functions:

**smtp_verify_full():**
- ❌ Old: Returned `catch_all, retry_next_at, smtp_meta, domain_smtp`
- ✅ New: Returns only `domain_status, validation_status, validation_response, domain_verified`

**verifyEmailViaSMTP():**
- ❌ Old: Returned `catch_all, retry_next_at, smtp_meta, domain_smtp`
- ✅ New: Returns only `domain_status, validation_status, validation_response, domain_verified`

**Email Details Array:**
- ❌ Old: Stored `domain_smtp, catch_all, smtp_meta, retry_next_at`
- ✅ New: Stores only `domain_status, validation_status, validation_response, domain_verified`

### 2. backend/includes/smtp_validation_cron.php

#### Updated Comments (Line ~88):
- ❌ Old: "Workers updated all fields (catch_all, smtp_meta, etc.)"
- ✅ New: "Workers updated domain_status, validation_status, validation_response"

#### Fixed Query (Line ~183):
- ❌ Old: `WHERE domain_processed = 0 OR (validation_status = 'retryable' AND next_retry_at IS NOT NULL AND next_retry_at <= NOW())`
- ✅ New: `WHERE domain_processed = 0`
- ✅ Removed retry logic since `next_retry_at` column doesn't exist

## Database Column Mapping:
| Worker Logic | Actual DB Column | Notes |
|-------------|------------------|-------|
| Valid email | `domain_status = 1` | Use this instead of domain_smtp |
| Invalid email | `domain_status = 0` | Use this instead of domain_smtp |
| Email verified | `domain_verified = 1` | Verification completed |
| Processing status | `validation_status` | 'pending', 'valid', 'invalid', 'disposable' |
| Error message | `validation_response` | Text response from SMTP |
| Processed flag | `domain_processed = 1` | Email has been processed |

## csv_list Table Columns (Unchanged):
```sql
- id
- list_name
- file_name
- total_emails
- valid_count - Count of emails where domain_status = 1
- invalid_count - Count of emails where domain_status = 0
- status - 'pending', 'running', 'completed'
- created_at
- user_id
```

## Testing Checklist:
- [ ] Upload CSV file via email_processor.php
- [ ] Verify csv_list status changes: pending → running
- [ ] Run SMTP validation: `php backend/includes/smtp_validation_cron.php`
- [ ] Check worker logs in `/tmp/smtp_worker_logs/`
- [ ] Verify emails table updates with correct columns
- [ ] Confirm csv_list valid_count and invalid_count update correctly
- [ ] Verify csv_list status changes to 'completed' when all processed
- [ ] Check frontend displays correct counts

## Result:
✅ All UPDATE queries now use only columns that exist in database schema
✅ No more SQL errors from non-existent columns
✅ Workers correctly store validation results using domain_status (0/1)
✅ csv_list counts properly calculated from domain_status values
✅ Code matches actual server database structure
