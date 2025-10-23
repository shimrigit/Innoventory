<?php
// Check if PhpSpreadsheet is available
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

/**
 * Apply sanity verification to the OCRsanity spreadsheet
 *
 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet The worksheet to verify
 * @param string $method The sanity method to use (Simple, LineTotal, NoLineTotal, Discount1, Discount2)
 */
function applySanityVerification($sheet, $method) {
    // Get the highest row with data
    $highestRow = $sheet->getHighestDataRow();
    if (!$highestRow) {
        $highestRow = $sheet->getHighestRow();
    }

    // Apply verification based on method
    switch ($method) {
        case 'Simple':
            applyDataTypeVerification($sheet, $highestRow);
            applyBarcodeSanityVerification($sheet, $highestRow);
            break;

        // Future methods will be added here
        case 'LineTotal':
        case 'NoLineTotal':
        case 'Discount1':
        case 'Discount2':
            // To be implemented
            break;
    }
}

/**
 * DataType Verification: Check columns F-J for numeric values
 * Non-numeric values get light purple background
 */
function applyDataTypeVerification($sheet, $highestRow) {
    $columnsToCheck = ['F', 'G', 'H', 'I', 'J'];

    for ($row = 2; $row <= $highestRow; $row++) {
        foreach ($columnsToCheck as $column) {
            $cell = $sheet->getCell("{$column}{$row}");
            $value = $cell->getValue();

            // Skip empty cells
            if ($value === null || $value === '') {
                continue;
            }

            // Check if value is numeric
            if (!is_numeric($value)) {
                // Paint cell background light purple
                $cell->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE6CCE6'); // Light purple
            }
        }
    }
}

/**
 * BarcodeSanity Verification: Check column D for valid barcodes
 * - Must be string with only digits
 * - Must not exceed 13 digits
 * Invalid barcodes get light yellow background
 */
function applyBarcodeSanityVerification($sheet, $highestRow) {
    for ($row = 2; $row <= $highestRow; $row++) {
        $cell = $sheet->getCell("D{$row}");
        $value = $cell->getValue();

        // Check if cell is empty
        if ($value === null || $value === '') {
            // Paint empty barcode cells light yellow
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFFCC'); // Light yellow
            continue;
        }

        // Convert to string for validation
        $valueStr = (string)$value;

        // Check if contains only digits
        if (!ctype_digit($valueStr)) {
            // Paint cell background light yellow (non-digit characters)
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFFCC'); // Light yellow
            continue;
        }

        // Check if length exceeds 13 digits
        if (strlen($valueStr) > 13) {
            // Paint cell background light yellow (too many digits)
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFFCC'); // Light yellow
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ocr_json']) && isset($_FILES['invoice_pdf'])) {

    // Get uploaded files
    $jsonFile = $_FILES['ocr_json'];
    $pdfFile = $_FILES['invoice_pdf'];

    // Validate JSON file upload
    if ($jsonFile['error'] !== UPLOAD_ERR_OK) {
        die('Error uploading JSON file');
    }

    // Validate JSON file type
    $jsonMimeType = mime_content_type($jsonFile['tmp_name']);
    $jsonExtension = strtolower(pathinfo($jsonFile['name'], PATHINFO_EXTENSION));

    if ($jsonExtension !== 'json' && !in_array($jsonMimeType, ['application/json', 'text/plain'])) {
        die('Error: The uploaded file is not a valid JSON file. Please upload a .json file.');
    }

    // Validate PDF file upload
    if ($pdfFile['error'] !== UPLOAD_ERR_OK) {
        die('Error uploading PDF file');
    }

    // Validate PDF file type
    $pdfMimeType = mime_content_type($pdfFile['tmp_name']);
    $pdfExtension = strtolower(pathinfo($pdfFile['name'], PATHINFO_EXTENSION));

    if ($pdfExtension !== 'pdf' || $pdfMimeType !== 'application/pdf') {
        die('Error: The uploaded file is not a valid PDF file. Please upload a .pdf file.');
    }

    // Read JSON file
    $jsonContent = file_get_contents($jsonFile['tmp_name']);
    $invoiceData = json_decode($jsonContent, true);

    if ($invoiceData === null) {
        die('Error: Invalid JSON file content. The file does not contain valid JSON data.');
    }

    // Extract SupplierName and ProcessDate from PDF filename
    $pdfFileName = $pdfFile['name'];

    // Remove .pdf extension
    $nameWithoutExt = pathinfo($pdfFileName, PATHINFO_FILENAME);

    // Find first space
    $firstSpace = strpos($nameWithoutExt, ' ');
    if ($firstSpace === false) {
        die('Error: PDF filename format is invalid. Expected format: "SupplierName dd-mm-yy ..." (must have at least one space)');
    }

    // Extract SupplierName (from start to first space)
    $supplierName = substr($nameWithoutExt, 0, $firstSpace);

    // Extract date part (after first space, take next 8 characters for dd-mm-yy)
    $afterFirstSpace = substr($nameWithoutExt, $firstSpace + 1);

    // Get first 8 characters as date (dd-mm-yy format)
    $datePart = substr($afterFirstSpace, 0, 8); // dd-mm-yy format

    // Validate that we have at least 8 characters for the date
    if (strlen($datePart) < 8) {
        die('Error: PDF filename format is invalid. Expected format: "SupplierName dd-mm-yy ..." (date part too short)');
    }

    // Convert dd-mm-yy to dd-mm-yyyy
    $dateParts = explode('-', $datePart);
    if (count($dateParts) !== 3) {
        die('Error: Date format in PDF filename is invalid. Expected: dd-mm-yy');
    }

    $day = $dateParts[0];
    $month = $dateParts[1];
    $year = '20' . $dateParts[2]; // Convert yy to yyyy

    $processDate = DateTime::createFromFormat('d-m-Y', "{$day}-{$month}-{$year}");
    if (!$processDate) {
        die('Error: Invalid date in PDF filename');
    }

    $processDateFormatted = $processDate->format('d-m-Y'); // dd-mm-yyyy

    // Create Excel file
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Stage 1: Add mandatory metadata with bold font

    // Column A - Labels (bold)
    $sheet->setCellValue('A1', 'InvoiceNo');
    $sheet->setCellValue('A2', 'supplierName');
    $sheet->setCellValue('A3', 'InvoiceDate');
    $sheet->setCellValue('A4', 'InvoiceTotal');
    $sheet->setCellValue('A5', 'Remark');

    // Column C to J - Headers (bold)
    $sheet->setCellValue('C1', 'Index');
    $sheet->setCellValue('D1', 'Barcode');
    $sheet->setCellValue('E1', 'ItemName');
    $sheet->setCellValue('F1', 'Qty');
    $sheet->setCellValue('G1', 'UnitPrice');
    $sheet->setCellValue('H1', 'LineTotal');
    $sheet->setCellValue('I1', 'Discount1');
    $sheet->setCellValue('J1', 'Discount2');

    // Make all headers bold
    $sheet->getStyle('A1:A5')->getFont()->setBold(true);
    $sheet->getStyle('C1:J1')->getFont()->setBold(true);

    // Stage 2: Map JSON data to Excel

    // B1 - Invoice Number (as text)
    $invoiceNumber = $invoiceData['invoice_number'] ?? '';
    $sheet->setCellValueExplicit('B1', $invoiceNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

    // B2 - Supplier Name (from PDF filename)
    $sheet->setCellValue('B2', $supplierName);

    // B3 - Invoice Date (as date)
    $invoiceDate = $invoiceData['invoice_date'] ?? '';
    if (!empty($invoiceDate)) {
        // Try to parse the date
        $dateObj = DateTime::createFromFormat('d/m/Y', $invoiceDate);
        if (!$dateObj) {
            // Try alternative format
            $dateObj = DateTime::createFromFormat('Y-m-d', $invoiceDate);
        }

        if ($dateObj) {
            // Store as Excel date
            $sheet->setCellValue('B3', \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dateObj));
            $sheet->getStyle('B3')->getNumberFormat()->setFormatCode('DD-MM-YYYY');
        } else {
            $sheet->setCellValue('B3', $invoiceDate);
        }
    }

    // B4 - Invoice Total (as number)
    $invoiceTotal = $invoiceData['invoice_total'] ?? 0;
    $sheet->setCellValue('B4', floatval($invoiceTotal));
    $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('#,##0.00');

    // B5 - Remark (leave empty)
    $sheet->setCellValue('B5', '');

    // Load supplier configuration for table mapping
    $suppliersFile = __DIR__ . '/../suppliers.json';
    $supplierConfig = null;
    $statusMessage = '';
    $rowsProcessed = 0;

    if (!file_exists($suppliersFile)) {
        $statusMessage = "Error: suppliers.json file not found";
    } else {
        $suppliers = json_decode(file_get_contents($suppliersFile), true);

        // Find supplier by name
        foreach ($suppliers as $supplier) {
            if ($supplier['supplierName'] === $supplierName) {
                $supplierConfig = $supplier;
                break;
            }
        }

        if ($supplierConfig === null) {
            $statusMessage = "Supplier {$supplierName} does not exist in suppliers.json config file";
        } elseif (!isset($supplierConfig['jsonToOcrSanity'])) {
            $statusMessage = "Supplier {$supplierName} does not have jsonToOcrSanity array";
        } else {
            // Validate column names in jsonToOcrSanity
            $validColumnNames = ['Barcode', 'ItemName', 'Qty', 'UnitPrice', 'LineTotal', 'Discount1', 'Discount2'];
            $jsonToOcrSanity = $supplierConfig['jsonToOcrSanity'];

            foreach ($jsonToOcrSanity as $columnName => $propertyNumber) {
                if (!in_array($columnName, $validColumnNames)) {
                    $statusMessage = "Column name {$columnName} in jsonToOcrSanity array of supplier {$supplierName} is not valid";
                    break;
                }
            }

            // If validation passed, process table data
            if (empty($statusMessage)) {
                $tableData = $invoiceData['table'] ?? [];

                foreach ($tableData as $rowObject) {
                    $index = $rowObject['index'] ?? null;

                    if ($index === null) {
                        continue; // Skip rows without index
                    }

                    $excelRow = $index + 1; // Row in Excel (index n -> row n+1)

                    // Write index to column C
                    $sheet->setCellValue("C{$excelRow}", $index);

                    // Map columns according to jsonToOcrSanity
                    foreach ($jsonToOcrSanity as $columnName => $propertyNumber) {
                        $propertyKey = (string)$propertyNumber;

                        if (!isset($rowObject[$propertyKey])) {
                            $statusMessage .= "Warning: Property '{$propertyNumber}' (for {$columnName}) does not exist in row {$index} of {$supplierName} invoice\n";
                            continue;
                        }

                        $value = $rowObject[$propertyKey];

                        // Special handling for ItemName - truncate to 15 characters
                        if ($columnName === 'ItemName') {
                            $value = mb_substr($value, 0, 15);
                        }

                        // Determine Excel column based on column name
                        $excelColumn = '';
                        switch ($columnName) {
                            case 'Barcode':
                                $excelColumn = 'D';
                                // Save as text to preserve long numbers (13 digits) without scientific notation
                                $sheet->setCellValueExplicit("{$excelColumn}{$excelRow}", $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                continue 2; // Skip the setCellValue below
                                break;
                            case 'ItemName':
                                $excelColumn = 'E';
                                break;
                            case 'Qty':
                                $excelColumn = 'F';
                                // Convert to number
                                $value = is_numeric($value) ? floatval($value) : $value;
                                break;
                            case 'UnitPrice':
                                $excelColumn = 'G';
                                // Convert to number with formatting
                                if (is_numeric($value)) {
                                    $sheet->setCellValue("{$excelColumn}{$excelRow}", floatval($value));
                                    $sheet->getStyle("{$excelColumn}{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                                    continue 2; // Skip the setCellValue below
                                }
                                break;
                            case 'LineTotal':
                                $excelColumn = 'H';
                                // Convert to number with formatting
                                if (is_numeric($value)) {
                                    $sheet->setCellValue("{$excelColumn}{$excelRow}", floatval($value));
                                    $sheet->getStyle("{$excelColumn}{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                                    continue 2; // Skip the setCellValue below
                                }
                                break;
                            case 'Discount1':
                                $excelColumn = 'I';
                                // Convert to number with formatting
                                if (is_numeric($value)) {
                                    $sheet->setCellValue("{$excelColumn}{$excelRow}", floatval($value));
                                    $sheet->getStyle("{$excelColumn}{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                                    continue 2; // Skip the setCellValue below
                                }
                                break;
                            case 'Discount2':
                                $excelColumn = 'J';
                                // Convert to number with formatting
                                if (is_numeric($value)) {
                                    $sheet->setCellValue("{$excelColumn}{$excelRow}", floatval($value));
                                    $sheet->getStyle("{$excelColumn}{$excelRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                                    continue 2; // Skip the setCellValue below
                                }
                                break;
                        }

                        // Write value to Excel
                        if (!empty($excelColumn)) {
                            $sheet->setCellValue("{$excelColumn}{$excelRow}", $value);
                        }
                    }

                    $rowsProcessed++;
                }

                // Set completion message
                if (empty($statusMessage)) {
                    $statusMessage = "Completed OCRsanity for {$supplierName} with {$rowsProcessed} rows";
                }
            }
        }
    }

    // Auto-fit all columns
    foreach (range('A', 'J') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }

    // Apply "Simple" sanity verification
    applySanityVerification($sheet, 'Simple');

    // Generate filename for Excel file
    $timestamp = date('dmY_His'); // ddmmyyyy_hhmmss
    $processDateFile = $processDate->format('d-m-Y'); // dd-mm-yyyy for filename
    $excelFileName = "OCRsanity_{$supplierName}_{$processDateFile}_{$timestamp}.xlsx";

    // Save directory
    $saveDirectory = __DIR__ . '/sanity_files/';
    $fullPath = $saveDirectory . $excelFileName;

    // Save Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save($fullPath);

    // Store PDF temporarily for verification (use original uploaded file)
    $tempPdfName = 'temp_' . $excelFileName . '.pdf';
    $tempPdfPath = $saveDirectory . $tempPdfName;
    move_uploaded_file($pdfFile['tmp_name'], $tempPdfPath);

    // Redirect to verification page
    header("Location: verify_ocrsanity.php?excel=" . urlencode($excelFileName) . "&pdf=" . urlencode($tempPdfName));
    exit;

    // Display success message (this code is now unreachable but kept for reference)
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>OCR Sanity Process - Success</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #28a745;
            padding-bottom: 10px;
        }
        .success-box {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info-box p {
            margin: 5px 0;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background: #0056b3;
        }
        .warning-box {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            white-space: pre-line;
        }
        .error-box {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h2>✅ OCR Sanity Process Completed Successfully</h2>

        <div class='success-box'>
            <strong>File saved:</strong> {$excelFileName}
        </div>";

    // Display status message
    if (!empty($statusMessage)) {
        // Check if it's an error or warning
        if (strpos($statusMessage, 'Error:') !== false || strpos($statusMessage, 'does not exist') !== false || strpos($statusMessage, 'does not have') !== false || strpos($statusMessage, 'not valid') !== false) {
            echo "<div class='error-box'><strong>⚠️ Status:</strong><br>" . nl2br(htmlspecialchars($statusMessage)) . "</div>";
        } elseif (strpos($statusMessage, 'Warning:') !== false) {
            echo "<div class='warning-box'><strong>⚠️ Warnings:</strong><br>" . nl2br(htmlspecialchars($statusMessage)) . "</div>";
        } else {
            echo "<div class='success-box'><strong>✅ Status:</strong><br>" . htmlspecialchars($statusMessage) . "</div>";
        }
    }

    echo "
        <div class='info-box'>
            <h3>📋 Extracted Information</h3>
            <p><strong>Supplier Name:</strong> {$supplierName}</p>
            <p><strong>Process Date:</strong> {$processDateFormatted}</p>
            <p><strong>Invoice Number:</strong> {$invoiceNumber}</p>
            <p><strong>Invoice Date:</strong> {$invoiceDate}</p>
            <p><strong>Invoice Total:</strong> {$invoiceTotal}</p>
            <p><strong>Rows Processed:</strong> {$rowsProcessed}</p>
        </div>

        <a href='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' class='back-link'>⬅️ Process Another File</a>
    </div>
</body>
</html>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OCR Sanity Process</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .form-group {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #555;
        }
        input[type="file"] {
            display: block;
            width: 100%;
            padding: 10px;
            border: 2px dashed #ccc;
            border-radius: 4px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        input[type="file"]:hover {
            border-color: #007bff;
        }
        button {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: background 0.3s;
        }
        button:hover {
            background: #218838;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning-box p {
            margin: 5px 0;
            font-size: 14px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 OCR Sanity Process</h2>

        <div class="info-box">
            <p><strong>ℹ️ Instructions:</strong></p>
            <p>This process converts OCR JSON data into a validated Excel sanity file.</p>
        </div>

        <div class="warning-box">
            <p><strong>⚠️ PDF Filename Format:</strong></p>
            <p>The invoice PDF filename must follow this format: <strong>SupplierName dd-mm-yy X.pdf</strong></p>
            <p>Examples:</p>
            <ul>
                <li>"FarmaDeal 20-10-25 A.pdf" → Supplier: FarmaDeal, Date: 20-10-2025</li>
                <li>"Globrands 07-09-25 A.pdf" → Supplier: Globrands, Date: 07-09-2025</li>
            </ul>
        </div>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="ocr_json">📄 Select OCR JSON File (.json)</label>
                <input type="file" id="ocr_json" name="ocr_json" accept=".json,application/json" required>
                <small style="display:block; margin-top:10px; color:#666;">Choose the OCRjson_*.json file</small>
            </div>

            <div class="form-group">
                <label for="invoice_pdf">📑 Select Invoice PDF File (.pdf)</label>
                <input type="file" id="invoice_pdf" name="invoice_pdf" accept=".pdf,application/pdf" required>
                <small style="display:block; margin-top:10px; color:#666;">Choose the invoice PDF (filename format: SupplierName dd-mm-yy X.pdf)</small>
            </div>

            <button type="submit">🚀 Generate OCR Sanity File</button>
        </form>
    </div>
</body>
</html>
