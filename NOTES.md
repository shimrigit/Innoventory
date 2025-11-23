# OCR Subproject Documentation

**Project Location:** `C:\xampp\htdocs\website`
**Last Updated:** October 28, 2025
**Status:** Phase 1 Complete - Ready for Commercial Layer

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Architecture & Data Flow](#architecture--data-flow)
3. [Directory Structure](#directory-structure)
4. [Key Components](#key-components)
5. [Configuration Files](#configuration-files)
6. [Verification Components](#verification-components)
7. [Code Flow Diagrams](#code-flow-diagrams)
8. [Important Notes](#important-notes)
9. [Next Steps](#next-steps)

---

## Project Overview

The OCR subproject is a comprehensive invoice processing system that extracts, validates, and verifies invoice data from PDF files using AI-powered OCR technology. The system handles the complete workflow from PDF upload to verified Excel output.

### Main Goals

1. **Extract** invoice data from PDF files using AI/OCR
2. **Transform** extracted data into structured Excel files
3. **Validate** data integrity using configurable verification methods
4. **Verify** invoice accuracy with side-by-side PDF/Excel comparison
5. **Save** verified data for downstream processing

### Technologies Used

- **Backend:** PHP 8.x with PhpSpreadsheet library
- **Frontend:** HTML5, JavaScript, Handsontable (Excel-like editing)
- **PDF Processing:** PDF.js (multi-page rendering with text overlay)
- **AI/OCR:** Anthropic Claude API (vision and text processing)
- **Data Storage:** Excel (.xlsx) files, JSON configuration files

---

## Architecture & Data Flow

### Three Main Stages

```
┌─────────────────────────────────────────────────────────────┐
│                     STAGE 1: AI/OCR                         │
│                  (AIocr Directory)                           │
└─────────────────────────────────────────────────────────────┘
                              │
                    PDF Invoice Upload
                              ↓
        ┌─────────────────────────────────────┐
        │  1. PDF → Images (page by page)     │
        │  2. Rectangle Crop Selection        │
        │  3. AI Vision Analysis              │
        │  4. Prompt-based Extraction         │
        │  5. Output: OCRjson_*.json          │
        └─────────────────────────────────────┘
                              │
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                STAGE 2: OCR SANITY                          │
│                (OCRsanity Directory)                         │
└─────────────────────────────────────────────────────────────┘
                              │
            JSON + PDF Files (with specific naming)
                              ↓
        ┌─────────────────────────────────────┐
        │  1. Supplier Name Extraction        │
        │  2. Configuration Validation        │
        │  3. JSON → Excel Conversion         │
        │  4. Data Type Verification          │
        │  5. Barcode Sanity Check            │
        │  6. Line Calculation Verification   │
        │  7. Total Sum Verification          │
        │  8. Output: OCRsanity_*.xlsx        │
        └─────────────────────────────────────┘
                              │
                              ↓
┌─────────────────────────────────────────────────────────────┐
│             STAGE 3: VERIFICATION                            │
│       (OCRsanity Directory - verify_ocrsanity.php)          │
└─────────────────────────────────────────────────────────────┘
                              │
                  Excel + PDF Side-by-Side
                              ↓
        ┌─────────────────────────────────────┐
        │  1. Side-by-side PDF/Excel View     │
        │  2. Editable Handsontable           │
        │  3. PDF Text Overlay (click-to-copy)│
        │  4. Total Equality Indicator        │
        │  5. Re-verify Functionality         │
        │  6. Save Verified Excel             │
        └─────────────────────────────────────┘
```

---

## Directory Structure

### `/AIocr` - Stage 1: AI-Powered OCR Extraction

```
AIocr/
├── multi_rectangle_crop_viewer.html    # UI for selecting invoice areas
├── process_ocr_ai.php                  # Main OCR processing logic
├── invoice_image_reader.php            # PDF to image conversion
├── save_crops.php                      # Save selected crop coordinates
├── config.json                         # OCR configuration
├── crops/                              # Saved crop coordinate definitions
├── ocr_extracted/                      # Output JSON files (OCRjson_*.json)
└── prompts/                            # AI prompt templates per supplier
```

**Key Files:**

- **`multi_rectangle_crop_viewer.html`**: Interactive tool to define rectangular regions on PDF pages for data extraction. Users select areas (invoice number, date, total, table, etc.) and save coordinates.

- **`process_ocr_ai.php`**: Core OCR processing engine. Reads PDF, applies crops, sends images to Claude AI with supplier-specific prompts, extracts structured data, outputs JSON.

- **`prompts/[SupplierName]_prompt.txt`**: Custom AI prompts per supplier to guide data extraction (e.g., "Extract invoice number from top-right", "Parse table with columns: Barcode, Name, Qty, Price").

### `/OCRsanity` - Stage 2 & 3: Validation & Verification

```
OCRsanity/
├── process_ocrsanity.php               # JSON → Excel conversion + validation
├── verify_ocrsanity.php                # Side-by-side verification UI
├── save_ocrsanity.php                  # Save edited Excel data
├── reverify_ocrsanity.php              # Re-run verification after edits
└── sanity_files/                       # Excel and temp PDF files
    ├── OCRsanity_*.xlsx                # Generated Excel files
    └── temp_*.pdf                      # Temporary PDF copies for verification
```

**Key Files:**

- **`process_ocrsanity.php`**:
  - Validates supplier configuration (suppliers.json)
  - Converts JSON to Excel with proper formatting
  - Applies verification methods (DataType, BarcodeSanity, LineSum, etc.)
  - Calculates Total Equality Indicator
  - Creates metadata sheet for tracking

- **`verify_ocrsanity.php`**:
  - Split-screen UI: Editable Excel (Handsontable) + PDF viewer
  - PDF text overlay for click-to-copy functionality
  - Multi-page PDF rendering with scroll
  - Total Equality Indicator (color-coded: green/orange/red)
  - Re-verify button to revalidate after edits

- **`save_ocrsanity.php`**: Saves Handsontable edits back to Excel file, preserving number formats and text types (especially barcodes).

- **`reverify_ocrsanity.php`**: Clears previous verification styling, re-applies validation rules, recalculates Total Equality Indicator, returns updated styles to frontend.

### `/PDFverification` - Legacy/Reference Implementation

```
PDFverification/
├── verify_invoice_ocrsanity.html       # Original verification UI
├── load_ocrsanity.php                  # Load Excel data as JSON
├── save_ocrsanity.php                  # Save Excel edits
└── uploads/                            # Uploaded files
```

**Note:** This directory contains the original/reference implementation. The current active system uses `/OCRsanity/verify_ocrsanity.php`.

### Root Configuration Files

```
website/
├── suppliers.json                      # Supplier configurations
├── shops.json                          # Shop/branch data
├── composer.json                       # PHP dependencies
└── vendor/                             # PhpSpreadsheet library
```

---

## Key Components

### 1. Supplier Configuration (`suppliers.json`)

Central configuration file defining how each supplier's invoices should be processed.

**Structure:**

```json
{
  "supplierName": "Tayari",
  "OCRsanityMethod": "Discount1",
  "jsonToOcrSanity": {
    "Barcode": 1,
    "ItemName": 2,
    "Qty": 3,
    "UnitPrice": 4,
    "LineTotal": 5,
    "Discount1": 6
  }
}
```

**Fields:**

- **`supplierName`** (string, required): Unique identifier, must match PDF filename prefix
- **`OCRsanityMethod`** (string, required): Validation method to use
  - `"Simple"`: DataType + BarcodeSanity
  - `"LineTotal"`: Simple + LineSum + TotalSum
  - `"Discount1"`: Simple + Discount1Calc + TotalSum
- **`jsonToOcrSanity`** (object, required): Maps JSON property numbers to Excel columns
  - Keys: Column names (Barcode, ItemName, Qty, UnitPrice, LineTotal, Discount1, Discount2)
  - Values: Property index in JSON table array (0-based)

### 2. PDF Filename Convention

**Format:** `SupplierName dd-mm-yy X.pdf`

**Examples:**
- `Tayari 26-03-25 A.pdf` → Supplier: Tayari, Date: 26-03-2025
- `FarmaDeal 20-10-25 B.pdf` → Supplier: FarmaDeal, Date: 20-10-2025

**Parsing Logic:**
1. Extract supplier name (everything before first space)
2. Extract date (8 characters after first space: dd-mm-yy)
3. Convert yy to yyyy (e.g., 25 → 2025)

### 3. OCR JSON Output Format

**Filename:** `OCRjson_[SupplierName]_[DateTime]_[RandomID].json`

**Structure:**

```json
{
  "invoice_number": "216235",
  "invoice_date": "25-03-2025",
  "invoice_total": "3172.04",
  "table": [
    {
      "index": 1,
      "0": "5000382114727",  // Property mapping defined in suppliers.json
      "1": "גולני גדול יבש",
      "2": "24",
      "3": "4.50",
      "4": "108.00",
      "5": "0"
    },
    // ... more rows
  ]
}
```

**Note:** Table rows use numeric keys (0, 1, 2...) which map to Excel columns via `jsonToOcrSanity` configuration.

### 4. Excel File Structure

**Filename:** `OCRsanity_[SupplierName]_[Date]_[Timestamp].xlsx`

**Sheets:**
1. **Main Sheet** (visible):
   - **Column A**: Labels (InvoiceNo, supplierName, InvoiceDate, InvoiceTotal, Remark)
   - **Column B**: Values
   - **Column C**: Index (row numbers)
   - **Columns D-J**: Table data (Barcode, ItemName, Qty, UnitPrice, LineTotal, Discount1, Discount2)
   - **Rows 1**: Headers (bold)
   - **Rows 2+**: Data with verification styling (backgrounds, borders)

2. **_Metadata Sheet** (hidden):
   - **A1**: "TotalDifference", **B1**: Calculated difference (B4 - sum(H))
   - **A2**: "SanityMethod", **B2**: Method name (Simple/LineTotal/Discount1)

**Cell Styling:**
- **Light Purple Background**: DataType verification failed (non-numeric value)
- **Light Yellow Background**: BarcodeSanity verification failed (invalid barcode)
- **Bold Black Border**: LineSum/Discount1Calc verification failed (calculation mismatch)

### 5. Verification Components

Modular validation units that check data integrity.

#### DataType Verification

**Purpose:** Ensure numeric columns contain valid numbers

**Columns Checked:** F, G, H, I, J (Qty, UnitPrice, LineTotal, Discount1, Discount2)

**Failure Action:** Apply light purple background (ARGB: FFE6CCE6)

**Code Location:**
- `process_ocrsanity.php`: Lines 51-79
- `reverify_ocrsanity.php`: Lines 191-210

#### BarcodeSanity Verification

**Purpose:** Validate barcode format and length

**Rules:**
- Must not be empty
- Must contain only digits (0-9)
- Must not exceed 13 digits

**Failure Action:** Apply light yellow background (ARGB: FFFFFFCC)

**Code Location:**
- `process_ocrsanity.php`: Lines 81-118
- `reverify_ocrsanity.php`: Lines 215-242

#### LineSum Verification

**Purpose:** Verify line total calculation: `Qty × UnitPrice = LineTotal`

**Formula:** `F × G = H`

**Tolerance:** ±0.01 (for floating-point precision)

**Failure Action:** Apply bold border (Border::BORDER_THICK) to cell H

**Code Location:**
- `process_ocrsanity.php`: Lines 120-158
- `reverify_ocrsanity.php`: Lines 247-273

#### Discount1Calc Verification

**Purpose:** Verify line total with discount: `Qty × UnitPrice × (1 - Discount1/100) = LineTotal`

**Formula:** `F × G × (1 - I/100) = H`

**Tolerance:** ±0.01

**Failure Action:** Apply bold border to cell H (columns F, G, H explicitly set)

**Special Feature:** Auto-fills column I with 0 if Discount1 not in jsonToOcrSanity

**Code Location:**
- `process_ocrsanity.php`: Lines 162-207
- `reverify_ocrsanity.php`: Lines 282-327

**Important Fix Applied:** Lines 187-190 & 201-204 use explicit cell reference `$sheet->getCell("H{$row}")` instead of `$lineTotalCell->getStyle()->getBorders()->getOutline()` to avoid border placement issues.

#### TotalSum Verification

**Purpose:** Calculate difference between invoice total (B4) and sum of all line totals (column H)

**Formula:** `B4 - sum(H2:H[lastRow]) = difference`

**Output:** Signed difference (can be negative or positive)

**Display:** Total Equality Indicator (only for LineTotal and Discount1 methods)

**Color Coding:**
- **Green**: difference = 0 (perfect match)
- **Orange**: -3 ≤ difference ≤ 3 (acceptable tolerance)
- **Red**: difference < -3 OR difference > 3 (requires attention)

**Code Location:**
- `process_ocrsanity.php`: Lines 209-237
- `reverify_ocrsanity.php`: Lines 329-357
- `verify_ocrsanity.php`: Lines 393-411 (indicator update logic)

**Important Fix Applied:** Changed from `abs($columnHSum - $invoiceTotal)` to `$invoiceTotal - $columnHSum` to show signed difference.

### 6. Sanity Methods

Combinations of verification components applied based on supplier configuration.

| Method | Components | Use Case |
|--------|-----------|----------|
| **Simple** | DataType + BarcodeSanity | Basic validation, no calculations |
| **LineTotal** | DataType + BarcodeSanity + LineSum + TotalSum | Standard invoices with Qty × Price |
| **Discount1** | DataType + BarcodeSanity + Discount1Calc + TotalSum | Invoices with discount column |

**Code Location:** `process_ocrsanity.php` Lines 16-50 (applySanityVerification function)

---

## Configuration Files

### suppliers.json Validation

**Early Validation** (before Excel creation) - Lines 323-459:

1. ✅ Check if suppliers.json file exists
2. ✅ Check if supplier (from PDF filename) exists in config
3. ✅ Check if supplier has `jsonToOcrSanity` array
4. ✅ Check if `OCRsanityMethod` is valid/implemented (Simple, LineTotal, Discount1)
5. ✅ Check if required fields exist in `jsonToOcrSanity` for selected method

**Required Fields per Method:**

- **Simple**: `Barcode`, `ItemName`
- **LineTotal**: `Barcode`, `ItemName`, `Qty`, `UnitPrice`, `LineTotal`
- **Discount1**: `Barcode`, `ItemName`, `Qty`, `UnitPrice`, `LineTotal` (Discount1 optional - auto-filled with 0)

**Error Handling:** If validation fails, user sees detailed error page with:
- Error message
- Supplier name extracted from PDF
- Instructions to fix configuration
- "Back to Upload" button

**No Excel file created on validation failure** - process stops immediately.

---

## Code Flow Diagrams

### Process OCR Sanity Flow

```
┌─────────────────────────────────────────────────────────────┐
│  process_ocrsanity.php                                      │
└─────────────────────────────────────────────────────────────┘

1. User uploads JSON + PDF files via HTML form
   ↓
2. Validate file types (JSON, PDF)
   ↓
3. Parse JSON content (invoice_number, invoice_date, invoice_total, table)
   ↓
4. Extract supplier name from PDF filename
   └─> Format: "SupplierName dd-mm-yy X.pdf"
   ↓
5. ⚠️ EARLY VALIDATION (Lines 323-459)
   ├─> suppliers.json exists?
   ├─> Supplier exists in config?
   ├─> jsonToOcrSanity array exists?
   ├─> OCRsanityMethod valid? (Simple/LineTotal/Discount1)
   └─> Required fields present in jsonToOcrSanity?
   │
   └─> ❌ IF VALIDATION FAILS:
       ├─> Show error page (Lines 392-458)
       └─> exit (NO Excel file created)
   │
   └─> ✅ IF VALIDATION PASSES: Continue...
   ↓
6. Create Excel file (Spreadsheet object)
   ↓
7. Write metadata (columns A-B, rows 1-5)
   ├─> InvoiceNo, supplierName, InvoiceDate, InvoiceTotal, Remark
   └─> Headers in row 1: Index, Barcode, ItemName, Qty, UnitPrice, LineTotal, Discount1, Discount2
   ↓
8. Map JSON table data to Excel columns (Lines 423-655)
   └─> Use jsonToOcrSanity to map property numbers to columns
   └─> Special handling:
       ├─> Column D (Barcode): setCellValueExplicit(..., TYPE_STRING) to prevent scientific notation
       ├─> Column E (ItemName): Truncate to 15 characters
       ├─> Columns F-J: Convert to float with formatting (#,##0.00)
   ↓
9. Auto-size columns A-J
   ↓
10. Auto-fill Discount1 column with 0 if needed (Lines 674-690)
    └─> If method = 'Discount1' AND 'Discount1' not in jsonToOcrSanity
        └─> Fill column I with 0 for all data rows
   ↓
11. Apply verification based on sanity method (Lines 692-693)
    ├─> Call applySanityVerification($sheet, $sanityMethod)
    └─> Returns $totalDifference for indicator
   ↓
12. Create hidden _Metadata sheet (Lines 697-703)
    ├─> Store TotalDifference (B1)
    └─> Store SanityMethod (B2)
   ↓
13. Save Excel file to sanity_files/ (Lines 718-719)
    └─> Filename: OCRsanity_{SupplierName}_{Date}_{Timestamp}.xlsx
   ↓
14. Save temporary PDF copy (Lines 721-724)
    └─> Filename: temp_{ExcelFileName}.pdf
   ↓
15. Redirect to verify_ocrsanity.php (Line 727)
    └─> Pass Excel and PDF filenames as URL parameters
```

### Verify OCR Sanity Flow

```
┌─────────────────────────────────────────────────────────────┐
│  verify_ocrsanity.php                                       │
└─────────────────────────────────────────────────────────────┘

1. Receive Excel and PDF filenames from URL parameters
   ↓
2. Load Excel file using PhpSpreadsheet (Lines 23-30)
   ├─> Read main sheet data as 2D array
   ├─> Read _Metadata sheet (TotalDifference, SanityMethod)
   └─> Extract cell styles (background colors, borders)
   ↓
3. Build styleMap for Handsontable (Lines 48-94)
   ├─> For each cell with background or border:
   │   ├─> backgroundColor: Extract ARGB, convert to hex
   │   └─> boldBorder: Check if any border is BORDER_THICK
   └─> Store as [{row, col, backgroundColor?, boldBorder?}, ...]
   ↓
4. Convert Excel data to JSON (Line 99)
   └─> JavaScript-readable array
   ↓
5. Render HTML page with split layout
   ├─────────────────────────┬─────────────────────────┐
   │   Left: Handsontable    │   Right: PDF Viewer     │
   │   (Editable Excel)      │   (Multi-page)          │
   ├─────────────────────────┴─────────────────────────┤
   │   Top: Header with buttons and indicator          │
   │   - Total Equality Indicator (if LineTotal/Discount1)
   │   - Save OCR Sanity button                        │
   │   - Re-verify button                              │
   └───────────────────────────────────────────────────┘
   ↓
6. Initialize Handsontable (Lines 420-464)
   ├─> Set data from Excel
   ├─> Enable editing (all cells editable)
   ├─> Apply custom renderer for cell styles (Lines 435-458)
   │   └─> Check styleMap, apply backgroundColor and/or boldBorder
   ├─> Set height: calc(100vh - 200px) for proper scrolling
   └─> Enable scrollbars (overflow: auto)
   ↓
7. Initialize PDF.js multi-page viewer (Lines 510-602)
   ├─> Load all pages sequentially
   ├─> Render pages one below the other (continuous scroll)
   ├─> Create text overlay for each page (Lines 557-602)
   │   └─> Extract text items from PDF
   │   └─> Position absolutely over rendered canvas
   │   └─> Enable click-to-copy to selected Handsontable cell
   └─> Container height: calc(100vh - 200px) for proper scrolling
   ↓
8. Initialize Total Equality Indicator (Lines 413-416)
   └─> If sanityMethod = 'LineTotal' OR 'Discount1':
       └─> Call updateTotalIndicator(totalDifference)
       └─> Set color: green (=0), orange (≤3), red (>3)
   ↓
9. User Actions:
   │
   ├─> Edit Cell:
   │   └─> Handsontable tracks changes in memory
   │
   ├─> Click "Save OCR Sanity" (Lines 608-662)
   │   ├─> Get updated data: hot.getData()
   │   ├─> Send to save_ocrsanity.php via fetch POST
   │   └─> Receive success message
   │
   └─> Click "Re-verify" (Lines 667-720)
       ├─> Get updated data: hot.getData()
       ├─> Send to reverify_ocrsanity.php via fetch POST
       ├─> Receive: {cellStyles[], totalDifference}
       ├─> Clear styleMap
       ├─> Populate styleMap with new styles
       ├─> Update Total Equality Indicator (Lines 697-700)
       └─> Re-render Handsontable: hot.render()
```

### Reverify OCR Sanity Flow

```
┌─────────────────────────────────────────────────────────────┐
│  reverify_ocrsanity.php                                     │
└─────────────────────────────────────────────────────────────┘

1. Receive POST data: {filename, data}
   ├─> filename: Excel file to update
   └─> data: 2D array from Handsontable (edited values)
   ↓
2. Load Excel file from sanity_files/
   ↓
3. Clear all previous verification styling (Lines 41-50)
   ├─> For rows 2 to end, columns A-J:
   │   ├─> Clear background: setFillType(FILL_NONE)
   │   └─> Clear borders: setBorderStyle(BORDER_NONE)
   └─> This ensures re-verification starts clean
   ↓
4. Update cells with new data from Handsontable (Lines 52-83)
   ├─> Map Handsontable indices to Excel rows/columns
   ├─> Skip empty values
   ├─> Special handling:
   │   ├─> Column D (Barcode): setCellValueExplicit(..., TYPE_STRING)
   │   └─> Columns B, F, G, H, I, J: Remove thousand separators, convert to float
   └─> ⚠️ CRITICAL FIX (Line 70-77): Remove commas from formatted numbers
       └─> Example: "3,172.04" → 3172.04 (prevents B4 value corruption)
   ↓
5. Get sanity method from _Metadata sheet (Lines 85-93)
   └─> Default to 'Simple' if not found
   ↓
6. Apply sanity verification (Line 96)
   ├─> Call applySanityVerification($sheet, $sanityMethod)
   └─> Returns $totalDifference
   ↓
7. Update _Metadata sheet (Lines 99-101)
   └─> Write new totalDifference to B1
   ↓
8. Save Excel file (Lines 104-105)
   ↓
9. Read back cell styles for frontend (Lines 107-150)
   ├─> For each cell:
   │   ├─> Check background color (FILL_NONE?)
   │   ├─> Check borders (BORDER_THICK?)
   │   └─> If either exists, add to cellStyles array
   └─> Return: [{row, col, backgroundColor?, boldBorder?}, ...]
   ↓
10. Send JSON response (Lines 152-157)
    └─> {success: true, cellStyles: [...], totalDifference: X.XX}
```

---

## Important Notes

### Critical Bug Fixes Applied

#### 1. Bold Border Placement Issue (Discount1Calc)

**Problem:** Borders appeared on column I (Discount1) instead of column H (LineTotal)

**Root Cause:** `getOutline()->setBorderStyle()` method had unexpected behavior

**Solution:** Use explicit cell references with individual border sides:
```php
$sheet->getCell("H{$row}")->getStyle()->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
$sheet->getCell("H{$row}")->getStyle()->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
$sheet->getCell("H{$row}")->getStyle()->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK);
$sheet->getCell("H{$row}")->getStyle()->getBorders()->getRight()->setBorderStyle(Border::BORDER_THICK);
```

**Files Modified:**
- `process_ocrsanity.php`: Lines 187-190, 201-204
- `reverify_ocrsanity.php`: Lines 307-310, 321-324

#### 2. TotalSum Calculation (Signed vs Absolute)

**Problem:** Total Equality Indicator always showed positive values

**Root Cause:** Using `abs($columnHSum - $invoiceTotal)` removed the sign

**Solution:** Changed to `$invoiceTotal - $columnHSum` to preserve sign

**Color Logic Fix:** Changed from `difference <= 3` to `Math.abs(difference) <= 3`

**Files Modified:**
- `process_ocrsanity.php`: Line 233
- `reverify_ocrsanity.php`: Line 347
- `verify_ocrsanity.php`: Line 406

#### 3. B4 Value Corruption During Re-verify

**Problem:** After re-verify, B4 value became 0 or incorrect, breaking TotalSum calculation

**Root Cause:** Handsontable sent formatted strings with thousand separators (e.g., "3,172.04"), which were written back as strings. When converted to float, commas caused parsing errors.

**Solution:** Strip commas before saving numeric columns:
```php
if (in_array($excelCol, [2, 6, 7, 8, 9, 10])) { // B, F, G, H, I, J
    $numericValue = str_replace(',', '', $value);
    if (is_numeric($numericValue)) {
        $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, (float)$numericValue);
    }
}
```

**Files Modified:**
- `reverify_ocrsanity.php`: Lines 70-77

#### 4. Validation Happens Too Late

**Problem:** Excel file created even when supplier doesn't exist in suppliers.json

**Root Cause:** Validation occurred after Excel file creation and data mapping

**Solution:** Moved validation to lines 323-459 (immediately after supplier name extraction, before Excel creation)

**Validation Order:**
1. Extract supplier name from PDF filename
2. **Validate configuration (EARLY - before Excel creation)**
3. If validation fails → Show error page, exit
4. If validation passes → Create Excel file, continue processing

**Files Modified:**
- `process_ocrsanity.php`: Lines 323-459 (early validation), removed duplicate validation at lines 669+

#### 5. Barcode Scientific Notation

**Problem:** Long barcodes (13 digits) saved as scientific notation (e.g., 5.00038E+12)

**Solution:** Use `setCellValueExplicit()` with `TYPE_STRING` for column D:
```php
$sheet->setCellValueExplicit("{$columnLetter}{$excelRow}", $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
```

**Files Modified:**
- `process_ocrsanity.php`: Lines 457-460
- `save_ocrsanity.php`: Lines 47-53
- `reverify_ocrsanity.php`: Lines 64-67

#### 6. Total Equality Indicator Not Showing for Discount1

**Problem:** Indicator only displayed for LineTotal method, not Discount1

**Solution:** Updated all conditionals to include both methods:
```php
// Before: if ($sanityMethod === 'LineTotal')
// After:  if ($sanityMethod === 'LineTotal' || $sanityMethod === 'Discount1')
```

**Files Modified:**
- `verify_ocrsanity.php`: Lines 330, 414, 697

### PhpSpreadsheet Deprecation Warnings

**Functions marked deprecated (but still functional):**
- `setCellValueByColumnAndRow()`
- `getCellByColumnAndRow()`

**Action:** Ignore warnings for now. Functions work correctly. Can be replaced in future with:
- `setCellValue()` with column letter conversion
- `getCell()` with column letter conversion

**Not critical** - no impact on functionality.

### Auto-fill Discount1 with Zeros

**Feature:** If supplier uses "Discount1" sanity method but jsonToOcrSanity doesn't have "Discount1" field:
- Column I (Discount1) automatically filled with 0 for all data rows
- Allows formula `Qty × UnitPrice × (1 - 0/100)` to work correctly (equivalent to no discount)

**Code Location:** `process_ocrsanity.php` Lines 674-690

---

## Recent Enhancements (Phase 2)

### 1. ActualUnitPrice Column (Column K)

**Added:** October 29, 2025

**Purpose:** Calculate the actual price paid per unit after applying discounts.

**Implementation:**

When saving OCR Sanity, a new column K is added with header "ActualUnitPrice" (bold).

**Calculation Logic by Sanity Method:**

| Sanity Method | Formula | Description |
|---------------|---------|-------------|
| **Simple** | `K = G` | Copies UnitPrice (no discount) |
| **LineTotal** | `K = G` | Copies UnitPrice (no discount) |
| **Discount1** | `K = G × (1 - I/100)` | Applies Discount1 percentage |

**Example (Discount1 method):**
- UnitPrice (G) = 100
- Discount1 (I) = 5
- ActualUnitPrice (K) = 100 × (1 - 5/100) = 100 × 0.95 = **95.00**

**Code Locations:**
- Initial calculation: `process_ocrsanity.php` Lines 703-747
- Re-verify calculation: `reverify_ocrsanity.php` Lines 118-162
- Number formatting: `#,##0.00` (two decimal places with thousand separators)

**Re-verify Behavior:**
- ActualUnitPrice is **always recalculated** when re-verify is clicked
- Updates dynamically if user edits UnitPrice (G) or Discount1 (I)
- Calculation uses the sanity method from cell B6 (not suppliers.json)

---

### 2. Sanity Method Display in Cell B6

**Added:** October 29, 2025

**Purpose:** Show which sanity method is being used and allow dynamic switching.

**Implementation:**

- **Cell A6:** Label "Sanity Method" (bold)
- **Cell B6:** Value showing the method (e.g., "Simple", "LineTotal", "Discount1")

**Initial Value:** Comes from `suppliers.json` → `OCRsanityMethod` field

**Dynamic Switching:**
- User can **edit B6** to change the sanity method
- On re-verify, system reads B6 (not suppliers.json)
- Allows testing different verification methods without re-uploading

**Code Locations:**
- A6/B6 creation: `process_ocrsanity.php` Lines 474, 698
- B6 validation: `reverify_ocrsanity.php` Lines 85-105
- Invalid method popup: `verify_ocrsanity.php` Lines 714-722

---

### 3. Dynamic Sanity Method for Re-verify

**Added:** October 29, 2025

**Purpose:** All re-verify operations use B6 value instead of original suppliers.json configuration.

**Breaking Change from Phase 1:**
- **Before:** Re-verify always used method from suppliers.json
- **After:** Re-verify uses method from cell B6 (user-editable)

**Workflow:**

1. **Initial Upload:**
   - Supplier "Tayari" has `OCRsanityMethod: "Discount1"` in suppliers.json
   - Excel created with B6 = "Discount1"
   - Verification applied using "Discount1" method

2. **User Changes Method:**
   - User edits B6 from "Discount1" to "LineTotal"
   - User clicks "Re-verify"
   - System validates B6 value
   - Verification applied using "LineTotal" method (from B6)
   - ActualUnitPrice recalculated using "LineTotal" formula

3. **Invalid Method Error:**
   - User edits B6 to "MyCustomMethod"
   - User clicks "Re-verify"
   - Popup appears:
   ```
   ❌ Invalid Sanity Method in cell B6!

   Current value: "MyCustomMethod"

   Please change cell B6 to one of these valid methods:
   Simple, LineTotal, Discount1

   Then click Re-verify again.
   ```

**Validation:**
- Valid methods: `['Simple', 'LineTotal', 'Discount1']`
- Empty B6: Defaults to "Simple"
- Invalid method: Shows error popup with valid options

**What Re-verify Does Based on B6:**

| Operation | Behavior |
|-----------|----------|
| **Verification Components** | Applies DataType, BarcodeSanity, LinSum/Discount1Calc based on B6 |
| **Bold Frames on Column H** | Applied based on B6 method logic |
| **ActualUnitPrice Calculation** | Uses formula matching B6 method |
| **Total Equality Indicator** | Shows/hides based on B6 method (LineTotal or Discount1) |
| **Metadata Sync** | Updates hidden _Metadata sheet with B6 value |

**Code Locations:**
- Read B6: `reverify_ocrsanity.php` Line 86
- Validate B6: `reverify_ocrsanity.php` Lines 88-105
- Apply verification: `reverify_ocrsanity.php` Line 110
- Update metadata: `reverify_ocrsanity.php` Lines 112-116

---

### 4. Column H (LineTotal) Behavior Clarification

**Important:** Column H is **NEVER recalculated** by the system.

**Sources of Column H Values:**
1. **OCR Extraction:** Initial value from AI/OCR JSON
2. **Manual Edit:** User types directly into cell H
3. **PDF Click-to-Copy:** User clicks highlighted text in PDF overlay

**Re-verify Does NOT Recalculate H:**
- Column H keeps its current value (from OCR or user edits)
- **Only verification is applied** (bold frames if mismatch detected)

**Verification Logic (Bold Frames on H):**

| Sanity Method | Verification Rule |
|---------------|-------------------|
| **Simple** | No verification on H (no bold frames) |
| **LineTotal** | Bold frame if `F × G ≠ H` |
| **Discount1** | Bold frame if `F × G × (1 - I/100) ≠ H` |

**Why H is Never Recalculated:**
- OCR-extracted LineTotal might be correct even if calculation differs slightly
- Users may need to manually correct OCR errors
- PDF values override calculated values (user knows the invoice better than formula)

**Code Locations:**
- LineSum verification: `reverify_ocrsanity.php` Function `applyLineSumVerification()`
- Discount1Calc verification: `reverify_ocrsanity.php` Function `applyDiscount1CalcVerification()`

---

### 5. Re-verify Data Synchronization Fix

**Fixed:** October 29, 2025

**Bug:** When user edited cells and clicked re-verify, ActualUnitPrice calculations were correct in Excel file but not displayed in Handsontable.

**Root Cause:** Server was recalculating ActualUnitPrice and saving to Excel, but only returning cell styles (colors/borders) to client, not the updated cell values.

**Solution:**
- Server now returns both `cellStyles` AND `cellData` in re-verify response
- Client reloads all cell values using `hot.loadData(data.cellData)`
- Handsontable display stays synchronized with Excel file

**Code Locations:**
- Server adds cellData: `reverify_ocrsanity.php` Lines 153-210
- Client reloads data: `verify_ocrsanity.php` Lines 702-705

**Before Fix:**
```
User changes G6 from 5 to 11
↓
Re-verify clicked
↓
Server calculates K6 = 11
↓
Server saves Excel ✓
↓
Server returns only styles ✗
↓
Handsontable still shows K6 = 5 ✗
```

**After Fix:**
```
User changes G6 from 5 to 11
↓
Re-verify clicked
↓
Server calculates K6 = 11
↓
Server saves Excel ✓
↓
Server returns styles + data ✓
↓
Handsontable reloads K6 = 11 ✓
```

---

### 6. Commercial Layer Configuration Setup

**Added:** October 29, 2025

**Purpose:** Prepare for multi-shop commercial layer development.

**New Files Created:**

1. **`configDir/shops_V2.json`**
   - Stores shop-specific configurations
   - Named "V2" to avoid confusion with legacy `shops.json`
   - Initial shop: "CountryMZ" with BackOfficeType "comax"

**Structure:**
```json
[
  {
    "shopName": "CountryMZ",
    "BackOfficeType": "comax"
  }
]
```

**Future Parameters (planned):**
- Shop address, contact info
- Tax rates, currency settings
- Default markup percentages
- Inventory thresholds
- Supplier relationships

**Code Location:** `C:\xampp\htdocs\website\configDir\shops_V2.json`

---

### Summary of Phase 2 Changes

| Feature | Files Modified | Lines Changed |
|---------|---------------|---------------|
| ActualUnitPrice Column | process_ocrsanity.php, reverify_ocrsanity.php | ~100 lines |
| Sanity Method in B6 | process_ocrsanity.php | ~5 lines |
| Dynamic B6 Re-verify | reverify_ocrsanity.php, verify_ocrsanity.php | ~50 lines |
| Re-verify Data Sync | reverify_ocrsanity.php, verify_ocrsanity.php | ~20 lines |
| Commercial Config | shops_V2.json (new file) | New file |

**Total Impact:** ~175 lines of code across 3 PHP files + 1 new config file

**Testing Status:** Ready for user testing with all three sanity methods

---

## Next Steps

### Immediate Tasks

1. **Test all sanity methods** with real supplier data:
   - Simple method (basic suppliers)
   - LineTotal method (standard invoices)
   - Discount1 method (invoices with discounts)

2. **Add more suppliers** to suppliers.json with correct configurations

3. **Create AI prompts** for each supplier in `AIocr/prompts/`

### Future Enhancements (Commercial Layer)

1. **Multi-shop support**: Process invoices for different branches/shops
   - Integration with shops.json
   - Shop-specific pricing verification
   - Inventory linkage

2. **Database integration**: Store verified invoices in MySQL/PostgreSQL
   - Invoice header table (invoice_id, supplier, date, total, status)
   - Invoice lines table (invoice_id, line_num, barcode, qty, price, etc.)
   - Verification audit trail

3. **Discount2 sanity method**: Implement second discount column verification
   - Formula: `Qty × UnitPrice × (1 - Discount1/100) × (1 - Discount2/100) = LineTotal`

4. **NoLineTotal sanity method**: For invoices without line-by-line totals
   - Skip LineSum/Discount1Calc verification
   - Only verify overall TotalSum

5. **Batch processing**: Process multiple invoices in one session
   - Queue system
   - Progress tracking
   - Error handling for partial failures

6. **Reporting dashboard**:
   - Daily/weekly/monthly invoice counts
   - Verification success rates
   - Common error patterns
   - Supplier performance metrics

7. **API endpoints**: Expose OCR functionality as REST API
   - POST /ocr/extract (PDF → JSON)
   - POST /ocr/validate (JSON → Excel + verification)
   - GET /ocr/status/{id} (Check processing status)

8. **Email notifications**: Alert users on completion/errors

9. **Advanced verification**:
   - Price history validation (flag unusual price changes)
   - Duplicate invoice detection
   - Cross-supplier barcode consistency checks

### Technical Debt

1. Replace deprecated PhpSpreadsheet methods (low priority)
2. Add unit tests for verification components
3. Refactor large functions into smaller, testable units
4. Add logging/debugging infrastructure
5. Implement proper error handling (try-catch blocks)
6. Add CSRF protection to forms
7. Sanitize all user inputs (especially file uploads)

---

## Developer Handoff Checklist

### For New Developers / Claude Sessions

**To Continue This Project:**

1. ✅ Read this NOTES.md file completely
2. ✅ Review `suppliers.json` structure - this is the core configuration
3. ✅ Understand the three-stage flow: AIocr → OCRsanity → Verification
4. ✅ Test the system end-to-end with a sample invoice:
   - Upload PDF to AIocr (multi_rectangle_crop_viewer.html)
   - Process with AI (process_ocr_ai.php) → generates JSON
   - Convert to Excel (process_ocrsanity.php) → generates OCRsanity_*.xlsx
   - Verify side-by-side (verify_ocrsanity.php) → edit and save

5. ✅ Check recent bug fixes (see "Critical Bug Fixes Applied" section)
6. ✅ Review verification components and their purposes
7. ✅ Understand sanity methods (Simple, LineTotal, Discount1)

**To Add a New Supplier:**

1. Add supplier entry to `suppliers.json`:
   ```json
   {
     "supplierName": "NewSupplier",
     "OCRsanityMethod": "LineTotal",
     "jsonToOcrSanity": {
       "Barcode": 1,
       "ItemName": 2,
       "Qty": 3,
       "UnitPrice": 4,
       "LineTotal": 5
     }
   }
   ```

2. Create AI prompt file: `AIocr/prompts/NewSupplier_prompt.txt`

3. Use crop viewer to define invoice layout: `AIocr/multi_rectangle_crop_viewer.html`

4. Save crop coordinates: `AIocr/crops/NewSupplier_crops.json`

5. Test with real invoice PDF

**To Add a New Verification Component:**

1. Create function in `process_ocrsanity.php` (e.g., `applyCustomVerification()`)
2. Duplicate function in `reverify_ocrsanity.php`
3. Add to sanity method in `applySanityVerification()` switch statement
4. Update required fields validation in early validation section
5. Test with sample data

**To Debug Issues:**

1. Check PHP error log: `C:\xampp\apache\logs\error.log`
2. Check browser console (F12) for JavaScript errors
3. Use `error_log()` statements in PHP for debugging
4. Check `sanity_files/` directory for generated files
5. Verify suppliers.json is valid JSON (use JSONLint)

---

## Contact & Support

**Project Files:** `C:\xampp\htdocs\website`

**Key Directories:**
- `/AIocr` - Stage 1 (OCR extraction)
- `/OCRsanity` - Stage 2 & 3 (validation & verification)
- `/PDFverification` - Legacy reference
- `/vendor` - Dependencies (PhpSpreadsheet)

**Configuration Files:**
- `suppliers.json` - Supplier configurations
- `shops.json` - Shop/branch data
- `composer.json` - PHP dependencies

**For Questions:**
- Review this NOTES.md file
- Check inline code comments
- Review git commit history for change context

---

## Commercial Layer Process (Stage 4)

**Added:** November 23, 2025
**Status:** ✅ Complete - Price Change & New Products Workflow Implemented

The Commercial Layer is the final stage of invoice processing. It takes verified OCR Sanity files and performs commercial analysis by comparing invoice prices against current market prices, identifying price changes, and detecting new products that need to be added to the inventory system.

### Commercial Layer Architecture

```
┌─────────────────────────────────────────────────────────────┐
│             STAGE 4: COMMERCIAL LAYER                        │
│          (commercialLayer Directory)                         │
└─────────────────────────────────────────────────────────────┘

Input: OCRsanity_*.xlsx + shop selection
                              ↓
        ┌─────────────────────────────────────┐
        │  1. Commercial Layer File Creation  │
        │     - Copy OCRsanity to CL file     │
        │     - Add ActualUnitPrice column    │
        │     - Add CHPprice column           │
        │     - Add PriceDiff column          │
        │  2. CHP Price Search (Puppeteer)    │
        │     - For each barcode, search CHP  │
        │     - Extract market price          │
        │     - Calculate price difference    │
        │  3. CL Verification Screen          │
        │     - Side-by-side view             │
        │     - Editable spreadsheet          │
        │     - Auto-save changes             │
        │  4. Save CL File                    │
        └─────────────────────────────────────┘
                              ↓
        ┌─────────────────────────────────────┐
        │  5. Generate PC and NP Files        │
        │     - Check PriceDiff column        │
        │     - Generate PC file if changes   │
        │     - Generate NP file if new items │
        │  6. Verify Price Changes            │
        │     - Split-screen interface        │
        │     - CHP search panel              │
        │     - Approve/reject changes        │
        │  7. Verify New Products             │
        │     - Department assignment         │
        │     - Margin calculation            │
        │     - Sale price determination      │
        └─────────────────────────────────────┘
                              ↓
        ┌─────────────────────────────────────┐
        │  8. Completion                      │
        │     - CL file saved                 │
        │     - PC file saved (if applicable) │
        │     - NP file saved (if applicable) │
        │     - Temp PDF deleted              │
        └─────────────────────────────────────┘
```

---

### Directory Structure

```
commercialLayer/
├── commercial_layer.html               # Shop selection interface
├── process_commercial_layer.php        # Main CL processing logic
├── verify_commercial_layer.php         # CL verification screen
├── save_commercial_layer.php           # Save CL file edits
├── generate_pc_np_files.php            # Generate PC and NP files
├── verify_price_changes.php            # PC verification screen
├── save_price_changes.php              # Save PC file edits
├── price_changes_complete.php          # PC completion screen
├── generate_np_file.php                # Generate NP file
├── verify_new_products.php             # NP verification screen
├── calculate_margins.php               # Margin calculation endpoint
├── save_new_products.php               # Save NP file edits
├── new_products_complete.php           # NP completion screen
├── getShopCHPprice.js                  # Puppeteer CHP price search
├── commercial_invoice_files/           # CL, PC, NP files directory
└── README.md                           # Commercial Layer documentation
```

---

### File Naming Conventions

**Commercial Layer (CL) File:**
- Format: `OCRsanity_[SupplierName]_[Date]_[Timestamp]_CL_[Timestamp].xlsx`
- Example: `OCRsanity_Gad_10-11-2025_10112025_110745_CL_23112025_085147.xlsx`
- Created from OCRsanity file with `_CL_[Timestamp]` suffix

**Price Change (PC) File:**
- Format: `OCRsanity_[SupplierName]_[Date]_[Timestamp]_CL_[Timestamp]_PRICE-CHANGE_[Timestamp].xlsx`
- Example: `OCRsanity_Gad_10-11-2025_10112025_110745_CL_23112025_085147_PRICE-CHANGE_231125_120000.xlsx`
- Created from CL file with `_PRICE-CHANGE_[Timestamp]` suffix

**New Products (NP) File:**
- Format: `OCRsanity_[SupplierName]_[Date]_[Timestamp]_CL_[Timestamp]_NEW-PRODUCTS_[Timestamp].xlsx`
- Example: `OCRsanity_Gad_10-11-2025_10112025_110745_CL_23112025_085147_NEW-PRODUCTS_231125_120000.xlsx`
- Created from CL file with `_NEW-PRODUCTS_[Timestamp]` suffix

**Timestamp Format:** `ddmmyy_hhmmss` (e.g., `231125_120000` = November 23, 2025 12:00:00)

---

### Commercial Layer File Structure (CL)

**Created from OCRsanity file with additional columns:**

| Column | Header | Source | Type | Description |
|--------|--------|--------|------|-------------|
| A-B | Metadata | OCRsanity | Various | InvoiceNo, SupplierName, InvoiceDate, InvoiceTotal, Remark, Sanity Method |
| C | Index | OCRsanity | Number | Row number |
| D | Barcode | OCRsanity | Text | Product barcode (13 digits max) |
| E | ItemName | OCRsanity | Text | Product name (truncated to 15 chars) |
| F | Qty | OCRsanity | Number | Quantity ordered |
| G | UnitPrice | OCRsanity | Number | Price per unit from invoice |
| H | LineTotal | OCRsanity | Number | Total for line item |
| I | Discount1 | OCRsanity | Number | Discount percentage (0-100) |
| J | Discount2 | OCRsanity | Number | Second discount percentage |
| K | ActualUnitPrice | OCRsanity | Number | Calculated actual unit price after discount |
| **L** | **CHPprice** | **CL Process** | **Number** | **Market price from CHP website** |
| **M** | **MinimalQty** | **CL Process** | **Number** | **Minimum order quantity (from CHP)** |
| **N** | **Origin** | **CL Process** | **Text** | **Product origin/manufacturer (from CHP)** |
| **O** | **PromoPrice** | **CL Process** | **Number** | **Promotional price if available (from CHP)** |
| **P** | **PriceDiff** | **CL Process** | **Text/Number** | **Price difference % or "Not Found"** |

**PriceDiff Calculation:**
- If product found in CHP: `((ActualUnitPrice - CHPprice) / CHPprice) × 100`
- If product not found in CHP: `"Not Found"`
- Formatted as percentage with 2 decimals (e.g., `5.25%`, `-3.10%`)

**Special Handling:**
- Columns L-P use ShopDefaultCity from shop configuration for CHP search
- CHP search powered by Puppeteer headless browser (getShopCHPprice.js)
- Temp PDF file created for verification (naming: `temp_[CLFileName].pdf`)

---

### Price Change File Structure (PC)

**Created from CL file, containing only rows with price differences:**

| Column | Header | Description |
|--------|--------|-------------|
| A-B | Metadata | Copied from CL (all rows, columns A-B only) |
| C | Original Index | Index from CL file (to track original position) |
| D | Barcode | Product barcode |
| E | ItemName | Product name |
| F | ItemERPName | ERP system name (editable by user) |
| G | ActualUnitPrice | Current invoice price |
| H | CHPprice | Market price from CHP |
| I | PriceDiff | Price difference percentage |
| J | ApprovedNewPrice | User-approved new price (editable) |
| K | InvoiceIdentifier | Invoice reference (without timestamp) |

**Row Population Logic:**
1. Copy columns A-B from CL file (ALL rows)
2. Populate columns C-K ONLY for rows where:
   - PriceDiff is numeric (not "Not Found")
   - PriceDiff ≠ 0
3. Rows are populated sequentially from row 2 onwards

**InvoiceIdentifier Extraction:**
- From CL filename: `OCRsanity_[SupplierName]_[Date]_[Timestamp]_CL_[Timestamp]`
- Extract: `[SupplierName]_[Date]` (remove both timestamps)
- Example: `Gad_10-11-2025` or `Gad_10-11-2025 A` (with letter suffix)

---

### New Products File Structure (NP)

**Created from CL file, containing only rows with "Not Found" in PriceDiff:**

| Column | Header | Description |
|--------|--------|-------------|
| A-B | Metadata | Copied from CL (all rows, columns A-B only) |
| C | Original Index | Index from CL file |
| D | Barcode | Product barcode (editable) |
| E | ItemName | Product name |
| F | ItemERPName | ERP system name (editable) |
| G | ActualUnitPrice | Current invoice price |
| H | Supplier | Supplier Hebrew name (from suppliers.json) |
| I | DepartmentName | Department (searchable dropdown - editable) |
| J | DepartmentMargin | Expected margin % (calculated, placeholder initially) |
| K | SalePrice | Sale price (editable) |
| L | ActualMargin | Actual margin % (calculated, placeholder initially) |
| M | InvoiceIdentifier | Invoice reference (without timestamp) |

**Row Population Logic:**
1. Copy columns A-B from CL file (ALL rows)
2. Populate columns C-M ONLY for rows where:
   - PriceDiff = "Not Found"
3. Rows are populated sequentially from row 2 onwards

**Margin Placeholders:**
- Initially set to "To be calculated"
- Orange border applied to cells J and L
- Calculated when user clicks "Calculate Margins" button

**Margin Calculation Formulas:**

**DepartmentMargin (Column J):**
- If DepartmentName is empty/null: `"Department Name is missing"`
- If DepartmentName is valid: Get `ExpectedMarginPercentage` from department config
- Convert to percentage format: `0.35 → 35%`
- Example: Department "Dairy" has ExpectedMarginPercentage = 0.40 → `40%`

**ActualMargin (Column L):**
- If SalePrice is empty/null/zero/negative: `"Sale Price is incorrect"`
- If SalePrice is valid: `((SalePrice/1.18) - ActualUnitPrice) / (SalePrice/1.18)`
- Convert to percentage format with 2 decimals: `0.3542 → 35.42%`
- Example: SalePrice = 100, ActualUnitPrice = 70 → `((100/1.18) - 70) / (100/1.18) → 17.52%`

**Supplier Hebrew Name Lookup:**
- Extract SupplierName from InvoiceIdentifier (first part before underscore)
- Example: `Gad_10-11-2025` → SupplierName = `Gad`
- Lookup in suppliers.json: `supplier.hebrewName`
- Fallback to English name if Hebrew name not found

---

### Configuration Files

**shops_V2.json Structure:**
```json
[
  {
    "shopName": "CountryMZ",
    "BackOfficeType": "comax",
    "shopDefaultCity": "Beer Sheva",
    "Departments": [
      {
        "DepartmentName": "Dairy",
        "ExpectedMarginPercentage": 0.35
      },
      {
        "DepartmentName": "Meat",
        "ExpectedMarginPercentage": 0.40
      }
    ]
  }
]
```

**Department Configuration Files:**
- Named: `[ShopName]_Departments.json`
- Example: `CountryMZ_Departments.json`
- Contains array of department names (for dropdown population)

**Shop Configuration Parameters:**
- `shopName`: Unique shop identifier
- `BackOfficeType`: Back-office system type ("comax", "priority", etc.)
- `shopDefaultCity`: City for CHP price search (e.g., "Beer Sheva", "Tel Aviv")
- `Departments`: Array of department objects with margin expectations

---

### CHP Price Search Integration

**Technology:** Puppeteer (headless Chrome browser)

**Implementation:** [getShopCHPprice.js](commercialLayer/getShopCHPprice.js:1)

**Search Process:**
1. Launch headless browser
2. Navigate to CHP website
3. Select city from shop configuration (`shopDefaultCity`)
4. Enter barcode in search field
5. Extract product data:
   - Market price (CHPprice)
   - Minimal quantity (MinimalQty)
   - Origin/manufacturer (Origin)
   - Promotional price (PromoPrice)
6. Return data or "Not Found" if product doesn't exist

**Search Endpoint:** Called by [process_commercial_layer.php](commercialLayer/process_commercial_layer.php)

**Error Handling:**
- Network timeout: Return "Search Error"
- Product not found: Return "Not Found"
- Invalid barcode: Return "Invalid Barcode"

**Performance:**
- Processes one barcode at a time
- Average search time: 2-3 seconds per barcode
- Progress indicator shown during batch processing

---

### Verification Screens

#### 1. CL Verification Screen

**File:** [verify_commercial_layer.php](commercialLayer/verify_commercial_layer.php)

**Features:**
- **Auto-save:** Changes saved automatically on cell blur (no manual save button)
- **Editable columns:** All columns editable (especially L-P for manual corrections)
- **PDF viewer:** Temp PDF displayed for reference
- **Real-time updates:** Cell edits immediately persisted to Excel file

**Layout:**
```
┌────────────────────────────────────────────────────────┐
│  Header: Commercial Layer Verification                │
│  File: [filename] | Shop: [shopname]                  │
│  [Conclude CL - no PC/NP] [Generate PC & NP Files]   │
└────────────────────────────────────────────────────────┘
┌─────────────────────┬──────────────────────────────────┐
│                     │                                  │
│  Excel Spreadsheet  │       PDF Viewer                 │
│  (Handsontable)     │       (PDF.js)                   │
│  Auto-save on blur  │       Multi-page scrollable      │
│                     │                                  │
└─────────────────────┴──────────────────────────────────┘
```

**Button Actions:**
- **"Conclude CL - no PC/NP"**: Save CL file, delete temp PDF, show completion message
- **"Generate PC & NP Files"**: Save CL file, delete temp PDF, generate PC and NP files

#### 2. PC Verification Screen

**File:** [verify_price_changes.php](commercialLayer/verify_price_changes.php)

**Features:**
- **Split-screen layout:** PC data (right) + CHP search panel (left)
- **CHP search panel:** Live price search by barcode (click-to-copy from table)
- **Editable columns:** F (ItemERPName), J (ApprovedNewPrice)
- **Color coding:** Price differences highlighted (red for increase, green for decrease)
- **Final action:** "Save Price Changes File" button

**Layout:**
```
┌────────────────────────────────────────────────────────┐
│  Header: Verify Price Changes                         │
│  [Save Price Changes File] - Right side               │
└────────────────────────────────────────────────────────┘
┌─────────────────────┬──────────────────────────────────┐
│                     │                                  │
│  CHP Search Panel   │    PC Data Table                 │
│  - City selector    │    (Columns C-K visible)         │
│  - Barcode input    │    Columns A-B hidden            │
│  - Search button    │    Editable: F, J                │
│  - Results display  │                                  │
│                     │                                  │
└─────────────────────┴──────────────────────────────────┘
```

**CHP Panel Features:**
- City dropdown (from shop config)
- Barcode input field (populated by clicking barcode in table)
- Search button (triggers Puppeteer search)
- Results display:
  - Product name
  - CHP price
  - Minimal quantity
  - Origin
  - Promotional price
- Loading indicator during search

#### 3. NP Verification Screen

**File:** [verify_new_products.php](commercialLayer/verify_new_products.php)

**Features:**
- **Split-screen layout:** NP data (right) + CHP search panel (left)
- **Searchable dropdown:** Department selection using Select2 library
- **Editable columns:** D (Barcode), F (ItemERPName), I (DepartmentName), K (SalePrice)
- **Margin calculation:** "Calculate Margins" button fills columns J and L
- **Final action:** "Save New Products File" button

**Layout:**
```
┌────────────────────────────────────────────────────────┐
│  Header: Verify New Products                          │
│  [Calculate Margins] [Save New Products File]         │
│  - Right side                                         │
└────────────────────────────────────────────────────────┘
┌─────────────────────┬──────────────────────────────────┐
│                     │                                  │
│  CHP Search Panel   │    NP Data Table                 │
│  (Same as PC)       │    (Columns C-M visible)         │
│                     │    Columns A-B hidden            │
│                     │    Editable: D, F, I, K          │
│                     │    Searchable dropdown: I        │
│                     │    Orange placeholders: J, L     │
│                     │                                  │
└─────────────────────┴──────────────────────────────────┘
```

**Margin Calculation Workflow:**
1. User fills in DepartmentName (column I) using searchable dropdown
2. User fills in SalePrice (column K)
3. User clicks "Calculate Margins"
4. System calculates:
   - DepartmentMargin (column J) from department config
   - ActualMargin (column L) from formula
5. Orange borders removed from calculated cells
6. Page reloads to show calculated values
7. User reviews and clicks "Save New Products File"

---

### Complete Workflow Example

**Scenario:** Processing Gad supplier invoice for CountryMZ shop

**Step 1: Upload OCR Sanity File**
- User uploads: `OCRsanity_Gad_10-11-2025_10112025_110745.xlsx`
- User selects shop: "CountryMZ"
- System creates: `OCRsanity_Gad_10-11-2025_10112025_110745_CL_23112025_085147.xlsx`

**Step 2: CL Processing**
- System copies all data from OCRsanity file
- System searches CHP for each barcode (using "Beer Sheva" city)
- System populates columns L-P with CHP data and price differences
- System creates temp PDF: `temp_OCRsanity_Gad_10-11-2025_10112025_110745_CL_23112025_085147.xlsx.pdf`
- System redirects to CL verification screen

**Step 3: CL Verification**
- User reviews CL file with PDF side-by-side
- User makes corrections if needed (auto-saved)
- User clicks "Generate PC & NP Files"
- System saves CL file
- System deletes temp PDF (CL stage concluded)

**Step 4: PC/NP Generation**
- System scans PriceDiff column (P)
- Finds 5 rows with numeric price differences (price changes)
- Finds 2 rows with "Not Found" (new products)
- System generates PC file with 5 rows of data
- System generates NP file with 2 rows of data
- System redirects to PC verification screen

**Step 5: PC Verification**
- User reviews 5 price changes
- User uses CHP panel to verify prices
- User approves new prices in column J
- User fills in ItemERPName in column F
- User clicks "Save Price Changes File"
- System saves PC file
- System redirects to PC completion screen
- User clicks "Continue to New Product Process"

**Step 6: NP Verification**
- User reviews 2 new products
- User uses CHP panel to search for market prices
- User fills in ItemERPName (column F)
- User selects Department from searchable dropdown (column I)
- User enters SalePrice (column K)
- User clicks "Calculate Margins"
- System calculates DepartmentMargin (40%) and ActualMargin (35.42%)
- User reviews calculations
- User clicks "Save New Products File"
- System saves NP file
- System redirects to NP completion screen

**Final Result:**
- CL file saved: ✅
- PC file saved: ✅ (5 price changes documented)
- NP file saved: ✅ (2 new products ready for inventory)
- Temp PDF deleted: ✅
- Commercial Layer process complete

---

### Key Features

**1. Auto-Save in CL Verification**
- Eliminates need for manual save button
- Cell changes persisted immediately on blur
- Reduces risk of data loss
- Improves user experience

**2. Searchable Department Dropdown**
- Uses Select2 jQuery plugin
- RTL (right-to-left) support for Hebrew
- Type-to-search functionality
- Clear selection option

**3. Click-to-Copy Barcodes**
- Barcodes in PC/NP tables are clickable
- Clicking copies barcode to CHP search panel
- Barcode field is also editable (dual functionality)
- Streamlines price verification workflow

**4. Dynamic Margin Calculation**
- Margins calculated on-demand (not automatic)
- User controls when to calculate
- Validates required fields (DepartmentName, SalePrice)
- Shows error messages in cells if validation fails

**5. Sequential Row Population**
- PC/NP files always start from row 2
- Rows populated consecutively (no gaps)
- Columns A-B preserved from CL file (all rows)
- Columns C onwards populated only for relevant rows

**6. Session State Management**
- CL filename stored in session
- Shop name preserved across screens
- NP filename stored for continuation
- Enables smooth multi-screen workflow

---

### File Lifecycle

**CL File:**
1. Created in [process_commercial_layer.php](commercialLayer/process_commercial_layer.php)
2. Edited in [verify_commercial_layer.php](commercialLayer/verify_commercial_layer.php) (auto-save)
3. **Saved and concluded** in [generate_pc_np_files.php](commercialLayer/generate_pc_np_files.php:53-55)
4. Remains in `commercial_invoice_files/` directory (permanent)

**Temp PDF:**
1. Created in [process_commercial_layer.php](commercialLayer/process_commercial_layer.php)
2. Displayed in [verify_commercial_layer.php](commercialLayer/verify_commercial_layer.php)
3. **Deleted** in [generate_pc_np_files.php](commercialLayer/generate_pc_np_files.php:57-62)
4. Purpose: Temporary reference for CL verification only

**PC File:**
1. Created in [generate_pc_np_files.php](commercialLayer/generate_pc_np_files.php)
2. Edited in [verify_price_changes.php](commercialLayer/verify_price_changes.php)
3. Saved in [save_price_changes.php](commercialLayer/save_price_changes.php)
4. Remains in `commercial_invoice_files/` directory (permanent)

**NP File:**
1. Created in [generate_np_file.php](commercialLayer/generate_np_file.php)
2. Edited in [verify_new_products.php](commercialLayer/verify_new_products.php)
3. Margin calculation in [calculate_margins.php](commercialLayer/calculate_margins.php)
4. Saved in [save_new_products.php](commercialLayer/save_new_products.php)
5. Remains in `commercial_invoice_files/` directory (permanent)

---

### Important Implementation Details

**1. InvoiceIdentifier Extraction**

InvoiceIdentifier is extracted from CL filename by removing both timestamps:

```php
// CL filename: OCRsanity_Gad_10-11-2025_10112025_110745_CL_23112025_085147.xlsx
// Extract: Gad_10-11-2025_10112025_110745
// Remove timestamp: _10112025_110745
// Result: Gad_10-11-2025
```

Regex pattern: `/_\d{8}_\d{6}$/` (removes `_ddmmyyyy_hhmmss` from end)

**2. Column A-B Preservation**

All generated files (PC, NP) copy columns A-B from CL file for ALL rows:
- Preserves metadata alignment
- Enables Excel formula references
- Maintains file structure consistency
- Columns A-B hidden in UI (not shown to user)

**3. Sequential Row Population (PC/NP)**

Critical rule: When populating columns C onwards in PC/NP files:
- First qualifying row from CL → Row 2 in PC/NP
- Second qualifying row from CL → Row 3 in PC/NP
- Third qualifying row from CL → Row 4 in PC/NP
- etc.

**Example:**
```
CL File:
Row 5: PriceDiff = 5.25%     → PC Row 2
Row 12: PriceDiff = -3.10%   → PC Row 3
Row 23: PriceDiff = 0.75%    → PC Row 4

NP File:
Row 8: PriceDiff = "Not Found"  → NP Row 2
Row 18: PriceDiff = "Not Found" → NP Row 3
```

**4. No Price Changes or New Products**

If CL file has:
- No price changes (all PriceDiff = 0 or "Not Found" only)
- No new products (no "Not Found" entries)

User clicks "Conclude CL - no PC/NP" button:
- CL file saved
- Temp PDF deleted
- Completion message shown
- No PC or NP files generated

---

### Technologies Used

**Backend:**
- PHP 8.x with PhpSpreadsheet library
- Puppeteer (Node.js) for CHP price scraping
- Session management for workflow state

**Frontend:**
- HTML5, CSS3, JavaScript
- Handsontable (Excel-like editing)
- PDF.js (PDF rendering)
- jQuery + Select2 (searchable dropdowns)

**External Integration:**
- CHP website (market price source)
- Headless Chrome (Puppeteer automation)

---

### Future Enhancements

**1. Bulk Price Approval**
- Select multiple price changes
- Approve all in one action
- Reject with reason codes

**2. Price History Tracking**
- Store historical price changes
- Show price trends over time
- Flag unusual price spikes

**3. Auto-Department Assignment**
- AI-based department suggestion from product name
- Historical department mapping
- Supplier-specific department rules

**4. Margin Optimization Suggestions**
- Recommend sale prices based on desired margin
- Compare margins across similar products
- Alert on below-minimum margin thresholds

**5. Multi-Supplier Batch Processing**
- Process multiple invoices in sequence
- Consolidated PC/NP reports
- Batch approval workflows

**6. ERP Integration**
- Direct export to back-office system (Comax, Priority)
- Automatic product creation
- Price update synchronization

---

### Troubleshooting

**Issue:** CHP search times out
- **Cause:** Network latency, website down, or city not available
- **Solution:** Check getShopCHPprice.js timeout settings, verify city name in shop config

**Issue:** NP file shows wrong margins
- **Cause:** Department config missing or incorrect ExpectedMarginPercentage
- **Solution:** Check [ShopName]_Departments.json file structure

**Issue:** PC file is empty even with price changes
- **Cause:** PriceDiff column has "Not Found" or zero values
- **Solution:** Verify CHP search completed successfully in CL stage

**Issue:** Columns A-B showing in PC/NP screens
- **Cause:** Display logic issue in verify_price_changes.php or verify_new_products.php
- **Solution:** Check `if ($colIdx < 2) continue;` condition in table rendering

**Issue:** Sequential row population broken
- **Cause:** Using CL row indices instead of sequential counter
- **Solution:** Verify `$pcRowNum++` or `$npRowNum++` incrementation logic

---

**Last Updated:** November 23, 2025
**Status:** ✅ Commercial Layer Complete - Full PC & NP Workflow Implemented
**Next Phase:** ERP integration, price history tracking, bulk approval workflows

---

## Stage 1: Invoice Pre-Process (PP) - PreProcess2 System

**Location:** `C:\xampp\htdocs\website\PreProcess2\`
**Version:** 2.0 (Beta Stage - Retailomatics System)
**Created:** November 23, 2025
**Status:** ✅ Complete

### Overview

PreProcess2 is a redesigned invoice pre-processing system that combines multi-page PDF invoices into single files with proper naming conventions. This system is the **first stage** in the Retailomatics Beta pipeline and prepares invoices for OCR analysis.

**Key Innovation:** Unlike the legacy PreProcess system, PreProcess2 **does not send emails**. All processed files remain in `preProcessDir` for the next stage (OA - OCR and Analysis).

### System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│           RETAILOMATICS BETA SYSTEM - STAGE 1                │
└─────────────────────────────────────────────────────────────┘

Input: Raw PDF scans in preProcessDir
    ↓
ProcessInvoices.php
- Display all PDFs with embedded preview
- User inputs: Supplier, Letter, Page Number, Date
- User can discard unwanted pages
    ↓
AppendAndRename.php
- Groups PDFs by Supplier + Letter + Date
- Merges pages using three-tier fallback (FPDI → PDFtk → Ghostscript)
- Renames: "{Supplier} {dd-mm-yy} {Letter}.pdf"
- Moves original pages to TempPP
- Keeps merged PDFs in preProcessDir
    ↓
Output: Properly named merged PDFs ready for Stage 2 (OA)
```

### Files

**[ProcessInvoices.php](PreProcess2/ProcessInvoices.php)**
- Entry point for the pre-processing workflow
- Displays all PDF files from `../preProcessDir`
- Visual PDF preview with embedded viewer
- Form inputs: Supplier (Hebrew autocomplete), Letter (A-J), Page Number (1-20), Date, Discard toggle

**[AppendAndRename.php](PreProcess2/AppendAndRename.php)**
- Processes submitted form data
- Groups PDFs by Supplier + Letter + Date
- Merges multi-page invoices using three fallback methods:
  1. **FPDI** (primary - PHP library, fast)
  2. **PDFtk** (fallback - handles compressed PDFs)
  3. **Ghostscript** (second fallback - handles complex operations)
- Outputs merged PDFs to `preProcessDir` with naming: `{Supplier} {dd-mm-yy} {Letter}.pdf`
- Moves original page files to `TempPP` directory
- Displays comprehensive results page

**[README.md](PreProcess2/README.md)**
- Complete system documentation
- Usage instructions
- Error handling guide
- Integration notes for Retailomatics Beta System

### File Naming Convention

**Input:** Any PDF filename (e.g., `scan001.pdf`, `IMG_2023.pdf`)

**Output:** `{SupplierEnglish} {dd-mm-yy} {Letter}.pdf`
- Example: `Tayari 15-11-25 A.pdf`
- Example: `FarmaDeal 23-11-25 B.pdf`

**Extraction Logic:**
1. User selects Supplier (Hebrew name from autocomplete)
2. System maps Hebrew name → English name using `suppliers.json`
3. User selects Letter (A-J) to distinguish multiple invoices on same date
4. User selects Date (defaults to today)
5. System formats date as dd-mm-yy

### Directory Structure

```
PreProcess2/
├── ProcessInvoices.php          # Entry point - display PDFs
├── AppendAndRename.php           # Processing logic - merge PDFs
└── README.md                     # Complete documentation

../preProcessDir/                 # Source and destination directory
├── [input PDFs]                  # Unprocessed PDF pages
├── [merged PDFs]                 # Output: "Supplier dd-mm-yy L.pdf"
└── TempPP/                       # Archived original page files
```

### PDF Merging Methods (Three-Tier Fallback)

**1. FPDI (Primary)**
- PHP library (setasign/fpdi)
- Fast, no external dependencies
- Cannot handle compressed PDFs

**2. PDFtk (Fallback)**
- Command-line tool
- Handles all PDF types including compressed
- Requires installation

**3. Ghostscript (Second Fallback)**
- Command-line tool
- Industry standard, very robust
- Requires installation

**Automatic Fallback Chain:**
- Try FPDI → If fails (compressed PDF) → Try PDFtk → If fails → Try Ghostscript → If all fail → Show error with installation links

### Results Reporting

After processing, the system displays:
- **Success items:** Document name, page count, page numbers used, method used (FPDI/PDFtk/Ghostscript)
- **Error items:** Document name, all error messages (FPDI/PDFtk/Ghostscript), installation links
- **Discarded files:** List of files moved to TempPP
- Link to process more invoices

### Example Workflow

**Scenario:** Processing 3 pages of a Tayari invoice from November 15, 2025

**Input Files:**
- `scan001.pdf`
- `scan002.pdf`
- `scan003.pdf`

**User Actions:**
| File | Supplier | Letter | Page | Date | Discard |
|------|----------|--------|------|------|---------|
| scan001.pdf | טיארי | A | 1 | 2025-11-15 | No |
| scan002.pdf | טיארי | A | 2 | 2025-11-15 | No |
| scan003.pdf | טיארי | A | 3 | 2025-11-15 | No |

**Processing:**
1. System groups all 3 files (same Supplier + Letter + Date)
2. Sorts by Page Number (1, 2, 3)
3. Merges using FPDI
4. Outputs: `Tayari 15-11-25 A.pdf` (3 pages)
5. Moves originals to `TempPP/`

**Result:**
- `preProcessDir/Tayari 15-11-25 A.pdf` ✅ (ready for OCR stage)
- `preProcessDir/TempPP/scan001.pdf` (archived)
- `preProcessDir/TempPP/scan002.pdf` (archived)
- `preProcessDir/TempPP/scan003.pdf` (archived)

### Key Differences from Legacy PreProcess

| Feature | Legacy (preProcess) | New (PreProcess2) |
|---------|---------------------|-------------------|
| **Email Sending** | Yes (via sendToOCR.php) | No - files stay in preProcessDir |
| **Session Variables** | Sets OCR email variables | No session variables |
| **Redirect** | Opens sendToOCR.php in new tab | Shows results page, link to process more |
| **Dependencies** | Gmail API, email templates | None - standalone system |
| **File Destination** | Emailed to OCR service | Remains in preProcessDir |
| **Complexity** | High (email integration) | Low (simple file processing) |

### Integration with Retailomatics Beta System

**Current Stage: Invoice Pre-Process (PP)** ✅
- Purpose: Combine and rename PDF invoices
- Input: Raw PDF scans from preProcessDir
- Output: Properly named merged PDFs in preProcessDir
- Status: Complete

**Next Stage: Invoice OCR and Analysis (OA)** 🔄
- Purpose: Extract invoice data using AI/OCR
- Input: Merged PDFs from preProcessDir
- Output: Structured JSON/Excel files
- Status: To be implemented

**Future Stage: Invoice ERP Packing (EP)** 📅
- Purpose: Package invoice data for ERP upload
- Input: Verified invoice data (JSON/Excel)
- Output: ERP-compatible files (Comax, Priority, etc.)
- Status: To be implemented

---

## Future Enhancements for PreProcess2

### 1. PDF Splitting Functionality

**Purpose:** Split large multi-page PDFs into individual pages for easier pre-processing

**Use Case:**
- User has a 20-page PDF containing multiple invoices
- Need to split it into individual pages before grouping and merging
- Each page can then be assigned to different suppliers/dates

**Planned Implementation:**
- New file: `SplitPDF.php`
- Allow user to upload large PDF
- Automatically split into individual pages
- Save pages to preProcessDir with sequential names
- User can then process pages through normal workflow

**Technical Approach:**
- Use FPDI to extract individual pages
- Alternative: PDFtk command `pdftk input.pdf burst output page_%02d.pdf`
- Alternative: Ghostscript extraction

### 2. JPG to PDF Conversion

**Purpose:** Convert scanned JPG images into PDF format before processing

**Use Case:**
- User has invoice scans as JPG files
- Need to convert to PDF before pre-processing
- Support batch conversion of multiple JPG files

**Planned Implementation:**
- New file: `ConvertJPGtoPDF.php`
- Allow user to upload JPG files (single or batch)
- Convert each JPG to individual PDF page
- Save converted PDFs to preProcessDir
- User can then process through normal workflow

**Technical Approach:**
- Use FPDF/FPDI to create PDF with embedded image
- Alternative: ImageMagick `convert image.jpg output.pdf`
- Alternative: Ghostscript conversion
- Maintain image quality and aspect ratio

**Additional Features:**
- Image rotation (90°, 180°, 270°)
- Image cropping/trimming
- Quality adjustment
- Batch processing with preview

---

**Last Updated:** November 23, 2025
**Status:** ✅ Phase 0 Complete - PreProcess2 Ready | ✅ Phase 1 Complete - OCR Sanity Ready | ✅ Phase 2 Complete - Commercial Layer Ready
**Next Phase:** PDF Split & JPG Conversion, then ERP integration, database migration, reporting dashboard
