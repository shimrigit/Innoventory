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

**Last Updated:** October 28, 2025
**Status:** ✅ Phase 1 Complete - Ready for Commercial Layer Development
**Next Phase:** Multi-shop support, database integration, reporting dashboard
