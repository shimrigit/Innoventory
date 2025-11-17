# Price Change Recommendations Algorithm

## Manager Summary

**Purpose:** Automatically recommend retail price adjustments when supplier costs change, while maintaining target profit margins.

**How it works:**
1. **Input:** Commercial Layer (CL) file containing matched invoice items with price list data
2. **Filter:** Only examine price changes exceeding minimum threshold (default: 3%)
3. **Exclude:** Skip government-regulated "supervised products" that cannot be freely priced
4. **Calculate:** Adjust retail prices proportionally to supplier cost changes to preserve original profit margins
5. **Validate:** Ensure recommended prices stay within acceptable margin boundaries:
   - **Minimum:** Department target margin (e.g., 30% for Dairy)
   - **Maximum:** Department margin + tolerance (e.g., 30% + 15% = 45%)
6. **Output:** Recommend price increase/decrease only if the change is meaningful (≥ 3%)

**Key principle:** Maintain profitability without overcharging customers. If maintaining the original margin would push us below the minimum (unprofitable) or above the maximum (unfair), we adjust to the nearest boundary instead.

---

## Overview
This document describes the complete algorithm used to analyze invoice price changes and generate price recommendations for the Commercial Layer system.

---

## File Structure

### Input Files
1. **CL File (Commercial Layer)** - Contains matched invoice items with price list data
2. **Shop Configuration** (`shops_V2.json`) - Contains shop-specific thresholds
3. **Department Configuration** (`{ShopName}_Departments.json`) - Contains expected margins per department
4. **Supervised Products** (`Supervised_products.json`) - List of price-regulated products

### Output File
**PC File (Price Change)** - Contains items with price changes above threshold, with recommendations

---

## Configuration Parameters

### From `shops_V2.json`:
- **PriceChangePercentageThreshold (PCPT)** - Minimum price change % to consider (default: 3%)
- **MarginTolerancePercentage** - Additional margin tolerance above department margin (default: 15%)

### From Department Configuration:
- **ExpectedMarginPercentage** - Target profit margin for each department (e.g., 0.35 = 35%)

### VAT Rate:
- **18%** - Current VAT rate in Israel (hardcoded)

---

## Algorithm Flow

## STAGE 1: Pre-Check for Price Changes

**Purpose:** Determine if any price changes warrant a PC file

**Process:**
1. Loop through all rows in CL file column P (PriceDiff)
2. Parse percentage value (remove % sign, convert to number)
3. Check if `|PriceDiff| > PCPT` for any row
4. **If NO rows exceed threshold:**
   - Display: "There are no price changes ({PCPT}% or below) to justify Price Change file"
   - Skip PC file creation entirely
   - Proceed to New Products file
5. **If ANY row exceeds threshold:**
   - Continue with PC file generation

---

## STAGE 2: Filter and Copy Rows to PC File

**Purpose:** Create PC file with only items that have significant price changes

**Column Mapping (CL → PC):**
```
CL Column A → PC Column A (as-is)
CL Column B → PC Column B (as-is)
CL Index → PC Column C (renamed "Original Index")
CL Barcode → PC Column D
CL ActualUnitPrice → PC Column E
CL OriginalUnitPrice → PC Column F
CL ItemERPName → PC Column G
CL Department → PC Column H
CL SalesPrice → PC Column I
CL PriceDiff → PC Column J
```

**New Calculated Columns:**
```
Column K: Old Mrgn (Old Margin)
Column L: New Mrgn (New Margin)
Column M: Dprtmnt Mrgn (Department Expected Margin)
Column N: Rec Price (Recommended Price)
Column O: Recommend (Recommendation Status)
Column P: Rec Mrgn (Recommended Margin)
```

**Filter Logic:**
- Only copy rows where `|PriceDiff| > PCPT`
- Preserve original Index number from CL file (do not re-index)

---

## STAGE 3: Process Each Row for Recommendations

### Step 1: Check Supervised Products

**Purpose:** Identify price-regulated products that cannot be changed

**Logic:**
```
FOR each supervised barcode in Supervised_products.json:
    IF invoice_barcode == supervised_barcode:
        MARK as supervised
    OR IF invoice_barcode is a suffix of supervised_barcode:
        MARK as supervised
```

**Suffix Match Example:**
- Supervised barcode: `7290000123456`
- Invoice barcode: `0123456` (7 digits)
- Match: Last 7 digits of supervised (`0123456`) == invoice barcode
- Result: SUPERVISED

**If Supervised:**
- Set Column O = "NO. supervised"
- Skip all remaining calculations for this row

---

### Step 2: Get Department Margin

**Purpose:** Retrieve expected profit margin for product's department

**Logic:**
```
SEARCH Department_Departments.json for:
    DepartmentName == Product.Department

IF FOUND:
    Column M = ExpectedMarginPercentage * 100 + "%"
    Store departmentMargin (decimal)
ELSE:
    Column M = "Department not found"
    Skip remaining calculations
```

**Example:**
- Department: "Dairy"
- Expected Margin: 0.35 (35%)
- Column M: "35%"

---

### Step 3: Calculate Old and New Margins

**Purpose:** Show profit margins before and after supplier price change

#### Old Margin (Column K):
```
Old Margin = ((SalesPrice / 1.18) - OriginalUnitPrice) / (SalesPrice / 1.18)
```

**Where:**
- `SalesPrice` = Current selling price (Column I)
- `OriginalUnitPrice` = Previous supplier price (Column F)
- `1.18` = VAT factor (18% VAT)

**Display:** Formatted as percentage with 2 decimals (e.g., "24.29%")

#### New Margin (Column L):
```
New Margin = ((SalesPrice / 1.18) - ActualUnitPrice) / (SalesPrice / 1.18)
```

**Where:**
- `ActualUnitPrice` = New supplier price (Column E)

**Display:** Formatted as percentage with 2 decimals (e.g., "20.34%")

---

### Step 4: Calculate Preliminary Price (PP) and Margin Borders

**Purpose:** Calculate initial recommended price and validate against margin limits

#### Preliminary Price (PP):
```
PP = SalesPrice * (1 + PriceDiff)
```

**Example:**
- SalesPrice = 15.00
- PriceDiff = 5% (0.05)
- PP = 15.00 * 1.05 = 15.75

#### Low Margin Border (LMB):
```
LMB = (ActualUnitPrice / (1 - DepartmentMargin)) * 1.18
```

**Purpose:** Minimum price to maintain department margin

**Example:**
- ActualUnitPrice = 10.00
- DepartmentMargin = 0.30 (30%)
- LMB = (10.00 / 0.70) * 1.18 = 16.86

#### High Margin Border (HMB):
```
HMB = (ActualUnitPrice / (1 - (DepartmentMargin + MarginTolerance/100))) * 1.18
```

**Purpose:** Maximum price allowing margin + tolerance

**Example:**
- ActualUnitPrice = 10.00
- DepartmentMargin = 0.30 (30%)
- MarginTolerance = 15 (15%)
- HMB = (10.00 / (1 - 0.45)) * 1.18 = 21.45

---

### Step 5: Apply Margin Validation (Calculate Final Price)

**Purpose:** Ensure recommended price stays within acceptable margin range

```
IF PP > HMB:
    FP = HMB  // Cap at high margin border
ELSE IF PP < LMB:
    FP = LMB  // Raise to low margin border
ELSE:
    FP = PP   // Use preliminary price as-is
```

**Why This Matters:**
- **PP > HMB**: Margin would be too high (unfair pricing)
- **PP < LMB**: Margin would be too low (unprofitable)
- **LMB ≤ PP ≤ HMB**: Price is commercially reasonable

---

### Step 6: Determine Recommendation

**Purpose:** Decide if price change should be recommended

#### Calculate Price Difference Percentage:
```
PriceDifference = |SalesPrice - FP|
PriceDifferencePercent = (PriceDifference / SalesPrice) * 100
```

#### Decision Logic:

##### Case 1: Slim Difference (NO CHANGE)
```
IF PriceDifferencePercent < PCPT:
    Column O = "NO. slim difference"
    Column N = SalesPrice (keep current price)
    Column P = (empty)
```

**Reason:** Change is too small to justify updating price

---

##### Case 2: Price Decrease (RECOMMEND)
```
IF SalesPrice > FP AND PriceDifferencePercent >= PCPT:
    Column O = "YES. decrease"
    Column N = FP (formatted to 2 decimals)
    Column P = ((FP/1.18 - ActualUnitPrice) / (FP/1.18)) * 100 + "%"
```

**Recommended Margin Calculation:**
```
RecMargin = ((FP / 1.18) - ActualUnitPrice) / (FP / 1.18)
```

**Display:** Formatted as percentage with 2 decimals (e.g., "30.16%")

---

##### Case 3: Price Increase (RECOMMEND)
```
IF SalesPrice < FP AND PriceDifferencePercent >= PCPT:
    Column O = "YES. increase"
    Column N = FP (formatted to 2 decimals)
    Column P = ((FP/1.18 - ActualUnitPrice) / (FP/1.18)) * 100 + "%"
```

**Same calculation as decrease, but selling price goes up**

---

## Complete Example Walkthrough

### Input Data:
- **Product:** Milk 1L
- **Department:** Dairy (Expected Margin: 30%)
- **ActualUnitPrice:** 10.00 ₪ (new supplier price)
- **OriginalUnitPrice:** 9.50 ₪ (old supplier price)
- **SalesPrice:** 15.00 ₪ (current selling price)
- **PriceDiff:** +5% (supplier increased price by 5%)
- **PCPT:** 3%
- **MarginTolerance:** 15%

### Step-by-Step Calculation:

#### 1. Pre-Check:
- |5%| > 3% ✓ → Include in PC file

#### 2. Check Supervised:
- Not in supervised list → Continue

#### 3. Department Margin:
- Column M = "30%"

#### 4. Calculate Margins:
**Old Margin:**
```
Old Margin = ((15.00 / 1.18) - 9.50) / (15.00 / 1.18)
          = (12.71 - 9.50) / 12.71
          = 3.21 / 12.71
          = 0.2525 = 25.25%
```
**Column K = "25.25%"**

**New Margin:**
```
New Margin = ((15.00 / 1.18) - 10.00) / (15.00 / 1.18)
          = (12.71 - 10.00) / 12.71
          = 2.71 / 12.71
          = 0.2132 = 21.32%
```
**Column L = "21.32%"**

#### 5. Calculate Prices:
**Preliminary Price:**
```
PP = 15.00 * (1 + 0.05) = 15.75 ₪
```

**Low Margin Border:**
```
LMB = (10.00 / (1 - 0.30)) * 1.18
    = (10.00 / 0.70) * 1.18
    = 14.29 * 1.18
    = 16.86 ₪
```

**High Margin Border:**
```
HMB = (10.00 / (1 - 0.45)) * 1.18
    = (10.00 / 0.55) * 1.18
    = 18.18 * 1.18
    = 21.45 ₪
```

#### 6. Validate Final Price:
```
PP (15.75) < LMB (16.86)
→ FP = 16.86 ₪  (raised to minimum margin)
```

#### 7. Make Recommendation:
```
PriceDiff = |15.00 - 16.86| = 1.86
PriceDiffPercent = (1.86 / 15.00) * 100 = 12.4%
12.4% >= 3% ✓
15.00 < 16.86 → Price increase needed
```

**Column O = "YES. increase"**
**Column N = "16.86"**

**Recommended Margin:**
```
RecMargin = ((16.86 / 1.18) - 10.00) / (16.86 / 1.18)
          = (14.29 - 10.00) / 14.29
          = 4.29 / 14.29
          = 0.30 = 30.00%
```
**Column P = "30.00%"**

---

## Summary of Recommendation Types

| Column O Value | Meaning | Column N | Column P |
|----------------|---------|----------|----------|
| **NO. supervised** | Product is price-regulated | (empty) | (empty) |
| **NO. slim difference** | Change < PCPT threshold | Current price | (empty) |
| **YES. decrease** | Recommend price reduction | New lower price | New margin % |
| **YES. increase** | Recommend price increase | New higher price | New margin % |
| **Department not found** | Cannot calculate margin | (empty) | (empty) |

---

## Key Insights

### Why margin borders exist:
1. **LMB**: Ensures profitability isn't compromised
2. **HMB**: Prevents excessive margins (unfair to customers)
3. **Range**: Allows flexibility while maintaining commercial viability

### Why PCPT threshold exists:
- Avoids changing prices for negligible differences
- Reduces price list churn and customer confusion
- Focuses effort on meaningful price changes

### Why supervised products are excluded:
- Regulatory restrictions (e.g., bread, milk, eggs in some countries)
- Government-controlled pricing
- Must be handled separately with authorities

---

## File Output Summary

After processing, the PC file shows:
- **Total rows copied:** All items with |PriceDiff| > PCPT
- **YES recommendations:** Items where validated price change ≥ PCPT
- **NO recommendations:** Items below threshold or supervised
- **Count of "YES" items:** Displayed to user for approval

**Example Message:**
```
"Price change file was created with price recommendation for 15 items"
```

This means 15 items have "YES. increase" or "YES. decrease" in Column O.

---

## Notes

- All percentage calculations preserve 2 decimal places
- Prices are formatted to 2 decimal places
- VAT rate (18%) is hardcoded for Israel
- Empty cells mean calculation was skipped (supervised or dept not found)
- Original Index preserved for traceability back to CL file

---

**Document Version:** 1.0
**Last Updated:** 2025-11-15
**System:** Commercial Layer - Price Change Module
