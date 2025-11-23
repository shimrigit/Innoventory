# PreProcess2 - Invoice Pre-Processing System

**Version:** 2.0 (Beta Stage - Retailomatics System)
**Created:** November 23, 2025
**Purpose:** Streamlined PDF invoice processing without email dependencies

---

## Overview

PreProcess2 is a redesigned invoice pre-processing system that combines multi-page PDF invoices into single files with proper naming conventions. Unlike the legacy PreProcess system, this version **does not send emails** and keeps all processed files in `preProcessDir` for the next stage.

---

## System Flow

```
┌─────────────────────────────────────────────────────────────┐
│           BETA STAGE - RETAILOMATICS SYSTEM                  │
└─────────────────────────────────────────────────────────────┘

Stage 1: Invoice Pre-Process (PP)
    ↓
    ProcessInvoices.php
    - Display all PDFs from preProcessDir
    - User inputs: Supplier, Letter, Page Number, Date
    - User can discard unwanted pages
    ↓
    AppendAndRename.php
    - Groups PDFs by Supplier + Letter + Date
    - Merges pages using FPDI/PDFtk/Ghostscript
    - Renames: "{Supplier} {dd-mm-yy} {Letter}.pdf"
    - Moves original pages to TempPP
    - Keeps merged PDFs in preProcessDir
    ↓
Stage 2: Invoice OCR and Analysis (OA)
    [Next stage - to be implemented]
    ↓
Stage 3: Invoice ERP Packing (EP)
    [Future stage - to be implemented]
```

---

## Files

### **ProcessInvoices.php**
- Entry point for the pre-processing workflow
- Displays all PDF files from `../preProcessDir`
- Provides form for user to specify invoice metadata
- Loads supplier list from `../suppliers.json`

**Features:**
- Visual PDF preview (embedded viewer)
- Supplier autocomplete from suppliers.json
- Letter dropdown (A-J)
- Page number selection (1-20)
- Date picker (defaults to today)
- Discard toggle (moves unwanted pages to TempPP)

### **AppendAndRename.php**
- Processes submitted form data
- Groups PDFs by Supplier, Letter, and Date
- Merges multi-page invoices using three fallback methods:
  1. **FPDI** (primary - PHP library)
  2. **PDFtk** (fallback - requires installation)
  3. **Ghostscript** (second fallback - requires installation)
- Outputs merged PDFs to `preProcessDir` with naming: `{Supplier} {dd-mm-yy} {Letter}.pdf`
- Moves original page files to `TempPP` directory
- Displays processing results with success/error messages

---

## File Naming Convention

**Input (from preProcessDir):**
- Any PDF filename (e.g., `scan001.pdf`, `IMG_2023.pdf`)

**Output (to preProcessDir):**
- Format: `{SupplierEnglish} {dd-mm-yy} {Letter}.pdf`
- Example: `Tayari 15-11-25 A.pdf`
- Example: `FarmaDeal 23-11-25 B.pdf`

**Extraction Logic:**
1. User selects Supplier (Hebrew name from autocomplete)
2. System maps Hebrew name to English name using `suppliers.json`
3. User selects Letter (A-J) to distinguish multiple invoices on same date
4. User selects Date (defaults to today)
5. System formats date as dd-mm-yy (e.g., 23-11-25)

---

## Directory Structure

```
PreProcess2/
├── ProcessInvoices.php          # Entry point - display PDFs
├── AppendAndRename.php           # Processing logic - merge PDFs
└── README.md                     # This file

../preProcessDir/                 # Source and destination directory
├── [input PDFs]                  # Unprocessed PDF pages
├── [merged PDFs]                 # Output: "Supplier dd-mm-yy L.pdf"
└── TempPP/                       # Moved original page files
```

---

## Key Differences from Legacy PreProcess

| Feature | Legacy (preProcess) | New (PreProcess2) |
|---------|---------------------|-------------------|
| **Email Sending** | Yes (via sendToOCR.php) | No - files stay in preProcessDir |
| **Session Variables** | Sets OCR email variables | No session variables |
| **Redirect** | Opens sendToOCR.php in new tab | Shows results page, link to process more |
| **Dependencies** | Gmail API, email templates | None - standalone system |
| **File Destination** | Emailed to OCR service | Remains in preProcessDir |
| **Complexity** | High (email integration) | Low (simple file processing) |

---

## Usage

### Step 1: Access the System
Navigate to: `http://localhost/website/PreProcess2/ProcessInvoices.php`

### Step 2: Review PDFs
- All PDFs from `preProcessDir` are displayed
- Each PDF shows embedded preview
- Input fields are pre-filled with defaults:
  - Letter: A
  - Page Number: 1
  - Date: Today

### Step 3: Fill Metadata
For each PDF page:
1. **Supplier**: Type to search Hebrew name (autocomplete from suppliers.json)
2. **Letter**: Select A-J (use different letters for multiple invoices on same date)
3. **Page Number**: Select 1-20 (order within merged document)
4. **Date**: Pick invoice date (defaults to today)
5. **Discard**: Toggle ON to discard this page (moves to TempPP)

### Step 4: Submit
- Click "Process Invoices" button
- System groups PDFs by Supplier + Letter + Date
- Merges pages in order by Page Number
- Creates merged PDFs with naming: `{Supplier} {dd-mm-yy} {Letter}.pdf`

### Step 5: Review Results
- Green boxes: Successfully created documents
- Red boxes: Failed documents (with error details)
- Orange boxes: Discarded files (moved to TempPP)
- Click "Process More Invoices" to return to step 1

---

## PDF Merging Methods

### 1. FPDI (Primary)
- **Type:** PHP library (setasign/fpdi)
- **Installation:** Via composer (already installed)
- **Pros:** Fast, no external dependencies
- **Cons:** Cannot handle compressed PDFs

### 2. PDFtk (Fallback)
- **Type:** Command-line tool
- **Installation:** Download from https://www.pdflabs.com/tools/pdftk-server/
- **Pros:** Handles all PDF types
- **Cons:** Requires separate installation

### 3. Ghostscript (Second Fallback)
- **Type:** Command-line tool
- **Installation:** Download from https://www.ghostscript.com/download/gsdnld.html
- **Pros:** Industry standard, very robust
- **Cons:** Requires separate installation

**Automatic Fallback Chain:**
1. Try FPDI
2. If FPDI fails (compressed PDF) → Try PDFtk
3. If PDFtk fails/unavailable → Try Ghostscript
4. If all fail → Show error with installation links

---

## Configuration

### Suppliers Mapping
The system uses `../suppliers.json` to map Hebrew names to English names:

```json
[
  {
    "supplierName": "Tayari",
    "hebrewName": "טיארי"
  },
  {
    "supplierName": "FarmaDeal",
    "hebrewName": "פרמה דיל"
  }
]
```

- User inputs: Hebrew name (טיארי)
- System outputs: English name (Tayari)
- Fallback: If no mapping found, uses Hebrew name as-is

---

## Example Workflow

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
5. Moves `scan001.pdf`, `scan002.pdf`, `scan003.pdf` to `TempPP/`

**Result:**
- `preProcessDir/Tayari 15-11-25 A.pdf` ✅ (ready for OCR stage)
- `preProcessDir/TempPP/scan001.pdf` (archived)
- `preProcessDir/TempPP/scan002.pdf` (archived)
- `preProcessDir/TempPP/scan003.pdf` (archived)

---

## Error Handling

### Common Errors

**1. "FPDI failed: CrossReferenceException"**
- **Cause:** PDF uses unsupported compression (e.g., object streams)
- **Solution:** Automatic fallback to PDFtk or Ghostscript

**2. "PDFtk is not installed"**
- **Cause:** PDFtk not in system PATH
- **Solution:** Install PDFtk Server or add to PATH

**3. "Ghostscript is not installed"**
- **Cause:** Ghostscript not in system PATH
- **Solution:** Install Ghostscript or add to PATH

**4. "Failed to load suppliers.json"**
- **Cause:** suppliers.json file missing or invalid JSON
- **Solution:** Check file exists at `../suppliers.json` and is valid JSON

**5. "No files were processed"**
- **Cause:** All PDFs were marked as discarded
- **Solution:** Uncheck discard toggle for files you want to process

---

## Integration with Retailomatics Beta System

### Current Stage: Invoice Pre-Process (PP) ✅
- **Purpose:** Combine and rename PDF invoices
- **Input:** Raw PDF scans from preProcessDir
- **Output:** Properly named merged PDFs in preProcessDir
- **Status:** Complete

### Next Stage: Invoice OCR and Analysis (OA) 🔄
- **Purpose:** Extract invoice data using AI/OCR
- **Input:** Merged PDFs from preProcessDir
- **Output:** Structured JSON/Excel files
- **Status:** To be implemented

### Future Stage: Invoice ERP Packing (EP) 📅
- **Purpose:** Package invoice data for ERP upload
- **Input:** Verified invoice data (JSON/Excel)
- **Output:** ERP-compatible files (Comax, Priority, etc.)
- **Status:** To be implemented

---

## Troubleshooting

**Issue:** PDFs not showing in ProcessInvoices.php
- **Check:** Files exist in `../preProcessDir`
- **Check:** Files have `.pdf` extension (lowercase)

**Issue:** Merged PDF has pages in wrong order
- **Check:** Page Number field is correct for each file
- **Check:** Page numbers are sequential (1, 2, 3, not 1, 1, 1)

**Issue:** Supplier name not autocompleting
- **Check:** Browser console for errors (F12)
- **Check:** suppliers.json exists and is valid JSON
- **Check:** suppliers.json has hebrewName field

**Issue:** Original files not moved to TempPP
- **Check:** TempPP directory exists and is writable
- **Check:** File permissions allow rename/move operations

---

## Future Enhancements

1. **Batch Upload:** Allow users to upload multiple PDFs directly
2. **OCR Preview:** Show extracted text before processing
3. **Auto-Grouping:** Suggest groupings based on visual similarity
4. **Duplicate Detection:** Warn if merged filename already exists
5. **Undo Functionality:** Restore original files from TempPP
6. **Audit Log:** Track all processing operations with timestamps
7. **Multi-User Support:** User authentication and session tracking

---

## Technical Notes

**PHP Requirements:**
- PHP 8.0+
- Composer dependencies (setasign/fpdi)

**Optional External Tools:**
- PDFtk Server (for compressed PDFs)
- Ghostscript (for complex PDF operations)

**Browser Compatibility:**
- Chrome/Edge: Full support
- Firefox: Full support
- Safari: PDF preview may require plugin

**File Size Limits:**
- PHP upload_max_filesize: Check php.ini
- PHP post_max_size: Check php.ini
- Recommended: 50MB+ for multi-page invoices

---

**Last Updated:** November 23, 2025
**Version:** 2.0 Beta
**Part of:** Retailomatics Beta System - Invoice Processing Pipeline
