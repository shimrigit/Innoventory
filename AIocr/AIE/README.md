# AI Enhancer (AIE) - OCR Testing Tool

## 🧪 Purpose
AI Enhancer is an **experimental testing tool** for improving OCR accuracy with OpenAI. It is completely separate from the production harmonized process and allows you to test different prompts and model configurations.

## 📁 Directory Structure
```
AIocr/AIE/
├── enhancer_ai.php           # Main interface
├── crop_tool.php              # PDF cropping interface
├── save_crops_aie.php         # Crop save handler
├── process_with_openai.php    # OpenAI processing logic
├── AIE_config.json            # Configuration file (EDITABLE)
├── invoice_pdf/               # Source PDF invoices
├── crops/                     # PNG crops from PDFs
├── ocr_extracted/             # JSON output files
└── README.md                  # This file
```

## 🚀 How to Use

### Option 1: Upload PDF and Crop
1. Navigate to `http://localhost/website/AIocr/AIE/enhancer_ai.php`
2. Select "PDF Invoice" option
3. Upload your PDF file
4. Mark crop regions (InvoiceNo, Date, Table1, Total, etc.)
5. Crops are processed with OpenAI automatically
6. View results and JSON output

### Option 2: Upload Pre-Cropped PNGs
1. Navigate to `http://localhost/website/AIocr/AIE/enhancer_ai.php`
2. Select "PNG Crops (Already Prepared)" option
3. Upload your PNG files (must have naming: InvoiceNo, Date, Table1, Total)
4. View results and JSON output

## ⚙️ Configuration

### Edit AIE_config.json to test different settings:

```json
{
  "model_settings": {
    "model": "gpt-4.1-mini",      // Change model here
    "max_tokens": 16000,           // Adjust token limit
    "temperature": 0.0,            // 0.0 = deterministic, 1.0 = creative
    "top_p": 1.00,
    "store": true,
    "response_format": {
      "type": "json_object"
    }
  },
  "prompt": {
    // Edit prompt sections here to test new approaches
    "system_prompt_part1": "...",
    "system_prompt_part2": "..."
  }
}
```

## 📤 Output

- **JSON files** are saved to: `AIE/ocr_extracted/`
- **Filename format**: `AIE_OCRjson_XXXX_dd-mm-yy_Z_ddmmyyyy_hhmmss.json`
  - Where `XXXX_dd-mm-yy_Z` comes from the PDF filename
  - Example: `AIE_OCRjson_Osem_22-12-25_A_27122025_143022.json`
- **PDF invoices**: `AIE/invoice_pdf/`
- **PNG crops**: `AIE/crops/`
  - Crop naming: `XXXX_dd-mm-yy_Z_InvoiceNo.png`, `XXXX_dd-mm-yy_Z_Table1.png`, etc.
  - Example: `Osem_22-12-25_A_InvoiceNo.png`

## ⚠️ Important Notes

1. **Completely Separate**: Changes to AIE do NOT affect the harmonized process
2. **Testing Only**: Use this tool to experiment with prompts and models
3. **API Key**: Uses the same OpenAI API key from `AIocr/config.json`
4. **No Supplier Config**: AIE doesn't require supplier configuration - it's just for testing

## 🔧 Testing Workflow

1. Modify `AIE_config.json` with your new prompt/settings
2. Process an invoice through AIE
3. Review the JSON output
4. Iterate on prompt until satisfied
5. **Then** implement changes in the production harmonized process

## 📝 File Naming Requirements

### PDF Files (Recommended Format)
While not strictly required, following the harmonized naming convention helps organize your tests:
- **Format**: `XXXX dd-mm-yy Z.pdf`
- **XXXX** = Supplier/Test name
- **dd-mm-yy** = Date
- **Z** = Sequence letter (A-Z)
- **Example**: `Osem 22-12-25 A.pdf`

This naming is preserved in all generated files (crops and JSON)!

### PNG Crop Files
Files must contain these keywords (case-insensitive):
- **InvoiceNo** - Invoice number
- **Date** - Invoice date
- **Table1** - First table section
- **Table2, Table3...** - Additional table sections (optional)
- **Total** - Invoice total

**Format when uploading pre-cropped files**:
- Recommended: `XXXX_dd-mm-yy_Z_InvoiceNo.png` (preserves test identity)
- Minimum: `anything_InvoiceNo.png` (basename will be extracted)

**Examples**:
- `Osem_22-12-25_A_InvoiceNo.png` (best practice)
- `Osem_22-12-25_A_Table1.png`
- `test_InvoiceNo.png` (acceptable)

## 🎯 Use Cases

- Test new prompt strategies
- Experiment with different OpenAI models
- Debug OCR accuracy issues
- Compare results with different configurations
- Test table extraction improvements

---

**Remember**: This is a testing tool. Once you've achieved good results, implement the changes in the production harmonized process.
