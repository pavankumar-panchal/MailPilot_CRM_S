# Template Field Reference - imported_recipients Table

## Available Fields for Templates

All placeholders are **case-insensitive**. You can use `[[Field]]`, `[[FIELD]]`, or `[[field]]`.

### Customer Information
| Placeholder | Database Column | Example Value |
|------------|-----------------|---------------|
| `[[CustomerID]]` | `CustomerID` | CUST123 |
| `[[BilledName]]` | `BilledName` | ABC Company Pvt Ltd |
| `[[Name]]` | `BilledName` (alias) | ABC Company Pvt Ltd |
| `[[Company]]` | `Company` or `Group Name` | Tech Corp |
| `[[ContactPerson]]` | `ContactPerson` | John Doe |
| `[[Email]]` | `Emails` | john@example.com |
| `[[Emails]]` | `Emails` | john@example.com |
| `[[Phone]]` | `Phone` | +91 9876543210 |
| `[[Cell]]` | `Cell` | +91 9876543210 |

### Address Information
| Placeholder | Database Column | Example Value |
|------------|-----------------|---------------|
| `[[Address]]` | `Address` | 123 Main Street |
| `[[Place]]` | `Place` | Bangalore |
| `[[District]]` | `District` | Bangalore Urban |
| `[[State]]` | `State` | Karnataka |
| `[[Pincode]]` | `Pincode` | 560001 |
| `[[Region]]` | `Region` | South |
| `[[Branch]]` | `Branch` | Bangalore Branch |

### Product Information
| Placeholder | Database Column | Example Value |
|------------|-----------------|---------------|
| `[[LastProduct]]` | `LastProduct` | Saral TDS Pro |
| `[[ProductGroup]]` | `ProductGroup` | Tax Software |
| `[[Edition]]` | `Edition` | Professional |
| `[[Category]]` | `Category` | Software |
| `[[Type]]` | `Type` | License |
| `[[UsageType]]` | `UsageType` | Commercial |
| `[[LastYear]]` | `LastYear` | 2024-25 |
| `[[LastLicenses]]` | `LastLicenses` | 5 |
| `[[LastRegDate]]` | `LastRegDate` | 2024-04-01 |

### Financial Information
| Placeholder | Database Column | Example Value |
|------------|-----------------|---------------|
| `[[Amount]]` | `Amount` | 5000 |
| `[[Price]]` | `Price` | 4237 |
| `[[Tax]]` | `Tax` | 763 |
| `[[NetPrice]]` | `NetPrice` | 5000 |
| `[[BillNumber]]` | `BillNumber` | INV-2024-001 |
| `[[BillDate]]` | `BillDate` | 2024-12-01 |
| `[[Days]]` | `Days` | 30 |

### Dealer Information
| Placeholder | Database Column | Example Value |
|------------|-----------------|---------------|
| `[[DealerName]]` | `DealerName` | Relyon Softech |
| `[[DealerEmail]]` | `DealerEmail` | dealer@relyon.com |
| `[[DealerCell]]` | `DealerCell` | +91 9876543210 |

### Executive Information
| Placeholder | Database Column | Example Value |
|------------|-----------------|---------------|
| `[[ExecutiveName]]` | `ExecutiveName` | Sales Executive |
| `[[ExecutiveContact]]` | `ExecutiveContact` | +91 9876543210 |

### System Fields
| Placeholder | Description | Example Value |
|------------|-------------|---------------|
| `[[CurrentDate]]` | Auto-generated current date | December 31st, 2025 |

## Field Aliases

The following aliases are automatically available:

- `[[Email]]` = `[[Emails]]` (singular/plural)
- `[[Name]]` = `[[BilledName]]` (short form)
- `[[Company]]` = `[[Group Name]]` or `[[Company]]` (with/without space)

## Case Insensitivity

All field names are case-insensitive. These all work the same:

```html
[[Email]]       ← Recommended (Title Case)
[[EMAIL]]       ← Works
[[email]]       ← Works
[[EmAiL]]       ← Works (not recommended)
```

## Example Template

```html
<!DOCTYPE html>
<html>
<body>
    <h1>Dear [[BilledName]],</h1>
    
    <p>Customer ID: [[CustomerID]]</p>
    <p>Email: [[Email]]</p>
    <p>Company: [[Company]]</p>
    <p>Location: [[District]], [[State]]</p>
    
    <h2>Invoice Details</h2>
    <table>
        <tr>
            <td>Bill Number:</td>
            <td>[[BillNumber]]</td>
        </tr>
        <tr>
            <td>Bill Date:</td>
            <td>[[BillDate]]</td>
        </tr>
        <tr>
            <td>Amount:</td>
            <td>₹[[Amount]]</td>
        </tr>
        <tr>
            <td>Days Overdue:</td>
            <td>[[Days]] days</td>
        </tr>
    </table>
    
    <h2>Product Information</h2>
    <p>Product: [[LastProduct]]</p>
    <p>Edition: [[Edition]]</p>
    <p>License Type: [[UsageType]]</p>
    
    <h2>Contact Your Dealer</h2>
    <p>Name: [[DealerName]]</p>
    <p>Email: [[DealerEmail]]</p>
    <p>Phone: [[DealerCell]]</p>
    
    <p>Date: [[CurrentDate]]</p>
</body>
</html>
```

## Best Practices

1. **Use Title Case**: `[[FieldName]]` for readability
2. **Test Preview**: Always check preview before sending campaign
3. **Handle Missing Data**: System automatically removes unfilled placeholders
4. **Use Aliases**: Use shorter aliases like `[[Email]]` instead of `[[Emails]]`
5. **Check Column Names**: Verify exact column names in database if unsure

## Troubleshooting

### Placeholder Not Filling?

1. Check if field exists in `imported_recipients` table
2. Verify field has data (not NULL or empty)
3. Check for typos in placeholder name
4. System fields are automatically filtered (id, import_batch_id, etc.)

### Preview Showing Empty Values?

1. Ensure import batch has data
2. Check `is_active = 1` for recipient records
3. Verify column has non-empty values
4. Test with different recipient email

### Field Names with Spaces?

Use aliases or exact name:
- `[[Group Name]]` - Works (exact match)
- `[[Company]]` - Works (alias automatically mapped)

---

**Last Updated**: December 31, 2025  
**System Version**: MailPilot CRM v2.0  
**Database Table**: `imported_recipients`
