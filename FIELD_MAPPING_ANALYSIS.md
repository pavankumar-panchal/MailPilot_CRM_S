# Field Mapping Analysis for TDS Renewal Template

## Template Requirements vs Available Data

### Template: `renewal_D-new.html`
Required fields: **13 placeholders**

| Placeholder | Current DB Value | Status | Solution |
|------------|------------------|--------|----------|
| `[[Company]]` | Empty | ❌ MISSING | Use `BilledName` or `Group Name` |
| `[[DISTRICT]]` | Empty | ❌ MISSING | Need from Excel or use `Region` |
| `[[Email]]` | ✅ Available | ✅ OK | Maps to `Emails` |
| `[[CustomerID]]` | Empty | ❌ MISSING | Need from Excel |
| `[[LastProduct]]` | Empty | ❌ MISSING | Need from Excel |
| `[[Edition]]` | Empty | ❌ MISSING | Need from Excel |
| `[[UsageType]]` | Empty | ❌ MISSING | Need from Excel |
| `[[Price]]` | Empty | ❌ MISSING | Can use `Amount` as fallback |
| `[[Tax]]` | Empty | ❌ MISSING | Calculate from Price (18% GST) |
| `[[NetPrice]]` | Empty | ❌ MISSING | Calculate Price + Tax |
| `[[DealerName]]` | Empty | ❌ MISSING | Use `ExecutiveName` |
| `[[DealerEmail]]` | Empty | ❌ MISSING | Need from Excel or config |
| `[[DealerCell]]` | Empty | ❌ MISSING | Use `ExecutiveContact` |

## Current Excel File: "TDS Updation Report 2024-25 -20250302.xlsx"

**This file likely contains TDS/Renewal specific data with these columns:**
- Customer ID
- Customer Name/Company
- Email
- District/Location
- Last Product Used
- Edition Type (Professional/Gold/Silver)
- Usage Type (Single/Network)
- License Count
- Price/Amount
- Dealer/Executive Details

## ACTION REQUIRED

### 1. Import the TDS Excel File
The current database only has invoice data. You need to import the TDS renewal Excel file to populate these fields:

```bash
# Import TDS file to database
# This will populate: CustomerID, LastProduct, Edition, UsageType, Price, Tax, NetPrice, District
```

### 2. Enhanced Field Mapping
Even with intelligent fallbacks, some critical fields are completely missing:

**CRITICAL MISSING:**
- `CustomerID` - Unique identifier for customer
- `LastProduct` - What product they currently use
- `Edition` - Professional/Gold/Silver edition
- `UsageType` - Single/Network usage
- `Price` - Base price for renewal
- `Tax` - GST amount
- `NetPrice` - Total with tax
- `District` - Customer location

**Currently using Fallbacks (Working):**
- `Company` → BilledName ✅
- `Email` → Emails ✅
- `DealerName` → ExecutiveName ✅
- `DealerCell` → ExecutiveContact ✅

## SOLUTION

### Option 1: Import Correct Excel File (RECOMMENDED)
Import "TDS Updation Report 2024-25 -20250302.xlsx" which should have all renewal fields.

### Option 2: Calculate Missing Fields
If only invoice data is available, calculate:
```php
Price = Amount
Tax = Amount * 0.18 (18% GST)
NetPrice = Amount + Tax
Edition = 'Professional' // Default
UsageType = 'Single' // Default
```

### Option 3: Hybrid Approach
- Import TDS Excel for customer-specific data (CustomerID, LastProduct, Edition, UsageType, District)
- Keep intelligent fallbacks for dealer info
- Calculate tax if not provided

## Files to Update

To implement Option 2 or 3, update these files:

### 1. `backend/includes/template_merge_helper.php`
Add calculated fields in `getEmailRowData()`:

```php
// Calculate tax and net price if Price available
if (!empty($data['Price'])) {
    $data['Tax'] = round($data['Price'] * 0.18, 2);
    $data['NetPrice'] = $data['Price'] + $data['Tax'];
} elseif (!empty($data['Amount'])) {
    // Use Amount as Price
    $data['Price'] = $data['Amount'];
    $data['Tax'] = round($data['Amount'] * 0.18, 2);
    $data['NetPrice'] = $data['Amount'] + $data['Tax'];
}

// Add default Edition if missing
if (empty($data['Edition'])) {
    $data['Edition'] = 'Professional';
}

// Add default UsageType if missing
if (empty($data['UsageType'])) {
    $data['UsageType'] = 'Single User';
}
```

## Database Schema Already Supports All Fields ✅

Your `imported_recipients` table already has columns for:
- CustomerID ✅
- LastProduct ✅
- Edition ✅
- UsageType ✅
- Price ✅
- Tax ✅
- NetPrice ✅
- District ✅
- DealerName ✅
- DealerEmail ✅
- DealerCell ✅

**The fields exist but are EMPTY because wrong Excel file was imported!**

## Next Steps

1. **Import the correct Excel file** (TDS Updation Report 2024-25 -20250302.xlsx)
2. **Verify column mapping** during import to ensure all 13 required fields are populated
3. **Test template preview** after import
4. If some fields still missing, implement calculated fallbacks
