# Template Field Matching - Data Availability Issue

## Problem Identified

The **template merge system is working correctly** with case-insensitive matching, BUT you're experiencing empty fields because:

### Your Imported Excel Data Contains:
✓ **Available Fields** (from `Final -naveen.xlsx`):
- `Emails` (Email addresses)
- `BilledName` (Customer name)  
- `Group Name` (Company/Group)
- `Amount` (Payment amount)
- `Days` (Days overdue)
- `BillNumber` (Invoice number)
- `BillDate` (Invoice date)
- `ExecutiveName` (Sales person)
- `ExecutiveContact` (Phone number)

### Missing Fields (Not in your Excel):
✗ `CustomerID`, `District`, `Edition`, `Price`, `Tax`, `NetPrice`, `LastProduct`, `UsageType`, `DealerName`, `DealerEmail`, `DealerCell`, `State`, `Pincode`, `Address`, `Company`, etc.

## Template Compatibility

### ✅ `Final -Naveen.html` - WORKS WITH YOUR DATA
**Requires:** Amount, Days, BilledName, BillNumber, BillDate, ExecutiveName, ExecutiveContact  
**Status:** ✓ All fields available in database  
**Result:** Preview shows correctly with all values filled

### ❌ `renewal_D-new.html` - MISSING DATA
**Requires:** Company, CustomerID, District, Edition, Price, Tax, NetPrice, LastProduct, UsageType, DealerName, DealerEmail, DealerCell  
**Status:** ✗ Most fields empty in database (not in Excel)  
**Result:** Preview shows empty values or placeholders removed

## Why This Happens

Your imported Excel file (`Final -naveen.xlsx`) appears to be an **INVOICE/PAYMENT file** format, which contains:
- Invoice details (BillNumber, BillDate, Amount)
- Customer basic info (BilledName, Group Name, Email)
- Payment tracking (Days overdue)
- Sales contact (ExecutiveName, ExecutiveContact)

The `renewal_D-new.html` template is designed for a **CUSTOMER/RENEWAL file** format, which would contain:
- Customer details (CustomerID, District, State, Address)
- Product details (LastProduct, Edition, UsageType)
- Pricing details (Price, Tax, NetPrice)
- Dealer details (DealerName, DealerEmail, DealerCell)

## Solutions

### Option 1: Use Correct Template for Your Data ✅ RECOMMENDED
Use `Final -Naveen.html` template with your current Excel import because it matches your data structure.

**Test Result:**
```
✓ All 7 placeholders filled successfully
✓ BilledName: 10K INFO DATA SOLUTIONS PRIVATE LIMITED - BKG
✓ BillNumber: RSL2024RL006315
✓ BillDate: 2025-03-08
✓ Amount: Rs.6313.00
✓ Days: 3 days
✓ ExecutiveName: Subramani M
✓ ExecutiveContact: 9449599704
```

### Option 2: Import Different Excel File
If you want to use `renewal_D-new.html`, import an Excel file that contains:
- CustomerID column
- District/State/Address columns
- Edition, Price, Tax, NetPrice columns
- LastProduct, UsageType columns
- DealerName, DealerEmail, DealerCell columns

### Option 3: Modify Template to Match Your Data
Edit `renewal_D-new.html` to use placeholders that exist in your data:

**Current (won't work):**
```html
<p>Customer ID: [[CustomerID]]</p>
<p>District: [[DISTRICT]]</p>
<p>Price: [[Price]]</p>
```

**Modified (will work):**
```html
<p>Bill Number: [[BillNumber]]</p>
<p>Bill Date: [[BillDate]]</p>
<p>Amount: [[Amount]]</p>
<p>Days Overdue: [[Days]]</p>
<p>Executive: [[ExecutiveName]] - [[ExecutiveContact]]</p>
```

### Option 4: Add Default Values in Database
If you want placeholders to show something instead of being removed, you can:
1. Update database to add default values
2. Or modify the merge function to show placeholders like "(Not Available)" instead of removing them

## Verification Commands

### Check what fields your imported Excel has:
```bash
php -r "
require_once 'backend/config/db.php';
\$result = \$conn->query('SELECT * FROM imported_recipients WHERE is_active=1 LIMIT 1');
\$row = \$result->fetch_assoc();
foreach (\$row as \$k => \$v) {
    if (\$v) echo \"✓ \$k = \$v\n\";
    else echo \"✗ \$k (empty)\n\";
}
"
```

### Test template merge:
```bash
cd backend/scripts
php test_second_template.php      # Test Final-Naveen.html
php test_renewal_template.php     # Test renewal_D-new.html
```

## System Status

✅ **Case-insensitive matching:** WORKING  
✅ **Field aliases (Email/Emails):** WORKING  
✅ **Template merge logic:** WORKING  
✅ **Database query:** WORKING  

⚠️ **Issue:** Template requires fields that don't exist in your imported Excel file

## Recommendations

1. **Use `Final -Naveen.html` template** for your current invoice/payment Excel imports ✅
2. **Create separate campaigns** for different Excel file types:
   - Invoice files → Use "Outstanding Payment" template
   - Customer files → Use "Renewal" template
3. **Document which Excel columns** each template requires
4. **Validate Excel structure** before importing to ensure it matches the template you plan to use

---

**The case-insensitive merge fix IS working correctly!** The "missing data" issue is simply because you're using a template designed for a different Excel file structure.
