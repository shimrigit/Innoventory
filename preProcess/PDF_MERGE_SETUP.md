# PDF Merge Setup Guide

## Problem
The error you encountered occurs when PDFs use modern compression techniques (object streams or cross-reference streams) that the free FPDI library cannot parse.

## Solution Implemented
The updated `ConcatMultiPageInvoice.php` now uses a **three-tier fallback system**:

1. **FPDI** (Primary) - Fast, built-in, works for most PDFs
2. **PDFtk** (Fallback 1) - Handles compressed PDFs, requires installation
3. **Ghostscript** (Fallback 2) - Universal PDF processor, requires installation

## Installation Options

### Option 1: Install PDFtk (Recommended)
**For Windows:**
1. Download PDFtk Server: https://www.pdflabs.com/tools/pdftk-server/
2. Run the installer (e.g., `pdftk_server-2.02-win-setup.exe`)
3. Add PDFtk to system PATH or update line 84 in the script:
   ```php
   $pdftkPath = 'C:\Program Files\PDFtk\bin\pdftk.exe';
   ```

**For Linux:**
```bash
sudo apt-get install pdftk
```

**For Mac:**
```bash
brew install pdftk-java
```

### Option 2: Install Ghostscript
**For Windows:**
1. Download Ghostscript: https://www.ghostscript.com/download/gsdnld.html
2. Install the 64-bit version
3. Update line 116 in the script if needed:
   ```php
   $gsPath = 'C:\Program Files\gs\gs10.03.1\bin\gswin64c.exe';
   ```

**For Linux:**
```bash
sudo apt-get install ghostscript
```

**For Mac:**
```bash
brew install ghostscript
```

## How It Works

1. The script first tries to merge PDFs using **FPDI**
2. If FPDI fails with `CrossReferenceException`:
   - It catches the error and tries **PDFtk**
   - If PDFtk is not installed or fails, it tries **Ghostscript**
   - If all methods fail, it displays a detailed error message

## Testing

After installing PDFtk or Ghostscript:

1. Try merging your PDFs again
2. Check the output - it will show which method was used:
   ```
   Document "Supplier 20-10-25 A.pdf" with 2 pages [Method: PDFtk]
   ```

## Error Messages

The script now provides detailed error messages if all methods fail, including:
- What went wrong with each method
- Links to download required tools
- Which documents failed to merge

## Alternative: Purchase FPDI PDF Parser

If you prefer not to install external tools, you can purchase the commercial FPDI PDF Parser ($59-$199):
https://www.setasign.com/fpdi-pdf-parser

This removes the compression limitation without requiring external dependencies.

## Technical Details

**Why does this happen?**
Modern PDF writers often use compression features from PDF 1.5+ specifications:
- Object streams (compress multiple objects together)
- Cross-reference streams (compress the PDF's index)

These features reduce file size but require more complex parsing logic that's not included in the free FPDI parser.

**Which method should I use?**
- **PDFtk**: Best for production, fast, reliable, maintains PDF quality
- **Ghostscript**: Universal, available everywhere, may re-compress PDFs (can change file size)
- **FPDI Commercial**: No external dependencies, integrates seamlessly with existing code
