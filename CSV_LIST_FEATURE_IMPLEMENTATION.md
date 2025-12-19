# CSV List Selection Feature - Implementation Summary

## Overview
Added functionality to select a specific CSV list when starting a campaign in the Master page. This allows targeted email sending to only recipients from the selected CSV list.

## Changes Made

### 1. Database Schema
**Note:** The `csv_list_id` column already exists in the `campaign_master` table.
- Column: `csv_list_id INT DEFAULT NULL`
- Index: `idx_csv_list_id` on `csv_list_id`

### 2. Backend Changes

#### `/backend/includes/campaign.php`
- ✅ Modified GET endpoint to JOIN with `csv_list` table and return `csv_list_name`
- ✅ Added `csv_list_id` handling in JSON POST/PUT operations
- ✅ Updated INSERT statement to include `csv_list_id` field

#### `/backend/public/campaigns_master.php`
- ✅ Added `csv_list_id` and `csv_list_name` to campaign query with LEFT JOIN
- ✅ Updated `getEmailCounts()` function to filter emails by `csv_list_id` if set
- ✅ Function now retrieves campaign's `csv_list_id` and applies filter to email counts

#### `/backend/includes/start_campaign.php`
- ✅ Modified to retrieve `csv_list_id` from campaign
- ✅ Added CSV list filter when counting eligible emails
- ✅ Updated error messages to indicate CSV list filtering

### 3. Frontend Changes

#### `/frontend/src/pages/Master.jsx`
- ✅ Added state variables for CSV list modal and selection:
  - `csvLists` - stores available CSV lists
  - `showCsvListModal` - controls modal visibility
  - `selectedCampaignId` - tracks which campaign is being started
  - `selectedCsvListId` - stores selected CSV list ID

- ✅ Added `fetchCsvLists()` function to load available CSV lists on component mount

- ✅ Updated `startCampaign()` function:
  - Now accepts optional `csvListId` parameter
  - Updates campaign with selected CSV list before starting
  - Sends API request to start campaign

- ✅ Added modal handlers:
  - `handleSelectCsvList()` - opens modal for CSV list selection
  - `handleConfirmCsvList()` - confirms selection and starts campaign

- ✅ Updated Send button to open CSV list selection modal

- ✅ Added CSV List Selection Modal:
  - Shows all available CSV lists with valid email counts
  - Option to select "All Lists" (no filter)
  - Clean, user-friendly interface

- ✅ Added visual indicator:
  - Displays selected CSV list name as a blue badge in campaign card
  - Shows alongside email count and status

## How It Works

1. **User clicks "Send" button** on a campaign in Master page
2. **CSV List Selection Modal opens** showing:
   - "All Lists" option (sends to all valid recipients)
   - Individual CSV lists with their valid email counts
3. **User selects a CSV list** (or chooses "All Lists")
4. **User clicks "Start Campaign"**
5. **System updates** the campaign's `csv_list_id` field
6. **Campaign starts** and sends emails only to recipients from the selected CSV list
7. **Selected CSV list** is displayed as a badge on the campaign card

## Email Filtering Logic

When a campaign has a `csv_list_id` set:
- `getEmailCounts()` filters emails: `WHERE e.csv_list_id = {selected_id}`
- `start_campaign.php` filters recipients when counting total emails
- Email workers respect the CSV list filter throughout the campaign

When `csv_list_id` is NULL:
- All valid, verified emails are eligible for the campaign
- No filtering is applied

## Benefits

1. ✅ **Targeted Campaigns**: Send to specific audience segments
2. ✅ **Flexibility**: Option to send to all lists or specific list
3. ✅ **Transparency**: Clear indication of which list is selected
4. ✅ **User-Friendly**: Simple modal interface for selection
5. ✅ **Real-time Counts**: Shows valid email counts for each list

## Testing Checklist

- [ ] Verify CSV list selection modal opens when clicking Send
- [ ] Confirm all CSV lists are displayed with correct email counts
- [ ] Test selecting "All Lists" (no filter)
- [ ] Test selecting a specific CSV list
- [ ] Verify campaign updates with selected `csv_list_id`
- [ ] Confirm emails are sent only to selected list recipients
- [ ] Check that selected list badge displays correctly
- [ ] Test with campaigns that have no CSV list selected (NULL)
- [ ] Verify email counts reflect the filtered list

## Files Modified

1. ✅ `/backend/includes/campaign.php`
2. ✅ `/backend/public/campaigns_master.php`
3. ✅ `/backend/includes/start_campaign.php`
4. ✅ `/frontend/src/pages/Master.jsx`

All changes are complete and ready for testing!
