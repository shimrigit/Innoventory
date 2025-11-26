<?php
/**
 * Generate Excel from OCRjson File (OJX Generator)
 *
 * This tool converts OCRjson files to Excel format (OJX files) for:
 * 1. Invoice setup process
 * 2. OCR sanity verification (copy/paste reference)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Initialize variables
$message = '';
$error = '';
$ojxFileName = '';
$fieldCount = 0;
$rowCount = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jsonFile'])) {
    $jsonFile = $_POST['jsonFile'];
    $jsonPath = __DIR__ . '/ocr_extracted/' . basename($jsonFile);

    try {
        // Verify file exists
        if (!file_exists($jsonPath)) {
            throw new Exception("JSON file not found: " . htmlspecialchars($jsonFile));
        }

        // Load and parse JSON
        $jsonContent = file_get_contents($jsonPath);
        $ocrData = json_decode($jsonContent, true);

        if ($ocrData === null) {
            throw new Exception("Failed to parse JSON file. Invalid JSON format.");
        }

        // Verify required fields
        if (!isset($ocrData['invoice_number']) || !isset($ocrData['invoice_date']) ||
            !isset($ocrData['invoice_total']) || !isset($ocrData['table'])) {
            throw new Exception("JSON file missing required fields (invoice_number, invoice_date, invoice_total, table)");
        }

        // Generate OJX filename with counter
        $baseFileName = pathinfo($jsonFile, PATHINFO_FILENAME); // Remove .json extension
        $counter = 0;
        $outputDir = __DIR__ . '/ocr_extracted/';

        // Find next available counter
        while (true) {
            $ojxFileName = $baseFileName . "_XL_{$counter}.xlsx";
            $ojxPath = $outputDir . $ojxFileName;

            if (!file_exists($ojxPath)) {
                break;
            }
            $counter++;
        }

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers (row 1)
        $sheet->setCellValue('A1', 'Invoice Param');
        $sheet->setCellValue('B1', 'Invoice Data');

        // Fill invoice metadata (rows 2-4)
        $sheet->setCellValue('A2', 'invoice_number');
        $sheet->setCellValue('B2', $ocrData['invoice_number']);

        $sheet->setCellValue('A3', 'invoice_date');
        $sheet->setCellValue('B3', $ocrData['invoice_date']);

        $sheet->setCellValue('A4', 'invoice_total');
        $sheet->setCellValue('B4', $ocrData['invoice_total']);

        // Process table data
        $table = $ocrData['table'];

        if (empty($table)) {
            throw new Exception("Table array is empty");
        }

        // Get first object to determine column structure
        $firstObject = $table[0];
        $columnHeaders = array_keys($firstObject);

        // Write table headers starting from column C (row 1)
        $colIndex = 3; // Column C = 3 (A=1, B=2, C=3)
        foreach ($columnHeaders as $header) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $header);
            $colIndex++;
        }

        // Write table data starting from row 2
        $rowIndex = 2;
        foreach ($table as $rowData) {
            $colIndex = 3; // Start from column C
            foreach ($columnHeaders as $header) {
                $value = $rowData[$header] ?? '';
                $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $value);
                $colIndex++;
            }
            $rowIndex++;
        }

        // Calculate statistics
        $fieldCount = count($columnHeaders) - 1; // Exclude "index" field
        $rowCount = count($table);

        // Save the Excel file
        $writer = new Xlsx($spreadsheet);
        $writer->save($ojxPath);

        $message = "✅ OJX file created successfully!";

    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Get list of JSON files in ocr_extracted directory
$jsonFiles = [];
$ocrExtractedDir = __DIR__ . '/ocr_extracted/';
if (is_dir($ocrExtractedDir)) {
    $files = scandir($ocrExtractedDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $jsonFiles[] = $file;
        }
    }
    // Sort by modification time (newest first)
    usort($jsonFiles, function($a, $b) use ($ocrExtractedDir) {
        return filemtime($ocrExtractedDir . $b) - filemtime($ocrExtractedDir . $a);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Excel from OCRjson (OJX Generator)</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 700px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }

        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }

        .info-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
            font-size: 14px;
        }

        .info-box li {
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        select:focus {
            outline: none;
            border-color: #667eea;
        }

        .file-count {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }

        .btn-generate {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-generate:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            font-weight: bold;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .result-info {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin-top: 15px;
        }

        .result-info h3 {
            color: #667eea;
            margin-bottom: 15px;
        }

        .result-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .result-row:last-child {
            border-bottom: none;
        }

        .result-label {
            font-weight: bold;
            color: #555;
        }

        .result-value {
            color: #333;
            font-family: 'Courier New', monospace;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 OJX Generator</h1>
        <p class="subtitle">Generate Excel from OCRjson Files</p>

        <div class="info-box">
            <h3>ℹ️ About OJX Files</h3>
            <ul>
                <li>OJX = OCRjson Excel file format</li>
                <li>Used for invoice setup and OCR sanity verification</li>
                <li>Provides structured view of OCR data in Excel format</li>
                <li>Auto-increments file counter if file exists</li>
            </ul>
        </div>

        <?php if ($message): ?>
            <div class="message success">
                <?= $message ?>
            </div>

            <?php if ($ojxFileName): ?>
                <div class="result-info">
                    <h3>✅ Generation Summary</h3>
                    <div class="result-row">
                        <span class="result-label">Output File:</span>
                        <span class="result-value"><?= htmlspecialchars($ojxFileName) ?></span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Fields (after "index"):</span>
                        <span class="result-value"><?= $fieldCount ?> fields</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Total Rows (indexes):</span>
                        <span class="result-value"><?= $rowCount ?> rows</span>
                    </div>
                    <div class="result-row">
                        <span class="result-label">Location:</span>
                        <span class="result-value">AIocr/ocr_extracted/</span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="jsonFile">Select OCRjson File:</label>
                <select name="jsonFile" id="jsonFile" required <?= empty($jsonFiles) ? 'disabled' : '' ?>>
                    <option value="">-- Select a JSON file --</option>
                    <?php foreach ($jsonFiles as $file): ?>
                        <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="file-count">
                    <?php if (empty($jsonFiles)): ?>
                        ⚠️ No JSON files found in ocr_extracted directory
                    <?php else: ?>
                        📁 <?= count($jsonFiles) ?> JSON file(s) available
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn-generate" <?= empty($jsonFiles) ? 'disabled' : '' ?>>
                🔄 Generate OJX File
            </button>
        </form>

        <a href="../" class="back-link">← Back to Main Menu</a>
    </div>
</body>
</html>
