<?php
/**
 * BOpack Retailomatics - Main Processing File
 *
 * Purpose: Process CL, PC, NP files and generate BOpack output files
 * - Invoice_list.xlsx
 * - Invoice_to_upload_*.xlsx (one per invoice)
 * - Price_change_list.xlsx (future)
 * - New_products_list.xlsx (future)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Error: Invalid request method. Please submit the form.');
}

// Get form data
$shopName = isset($_POST['shopName']) ? trim($_POST['shopName']) : '';
$processDate = isset($_POST['processDate']) ? trim($_POST['processDate']) : '';

if (empty($shopName) || empty($processDate)) {
    die('Error: Shop name and process date are required.');
}

// Convert process date from Y-m-d to dd-mm-yy format for file matching
$processDateObj = DateTime::createFromFormat('Y-m-d', $processDate);
if (!$processDateObj) {
    die('Error: Invalid process date format.');
}
$processDateShort = $processDateObj->format('d-m-y'); // dd-mm-yy for output files
$processDateFull = $processDateObj->format('d-m-Y'); // dd-mm-yyyy for input file matching

// Load shop configuration
$shopsConfigPath = __DIR__ . '/../configDir/shops_V2.json';
if (!file_exists($shopsConfigPath)) {
    die('Error: shops_V2.json configuration file not found.');
}

$shopsConfig = json_decode(file_get_contents($shopsConfigPath), true);
if (!$shopsConfig) {
    die('Error: Failed to parse shops_V2.json.');
}

// Find shop configuration
$shopConfig = null;
foreach ($shopsConfig as $shop) {
    if ($shop['ShopName'] === $shopName) {
        $shopConfig = $shop;
        break;
    }
}

if (!$shopConfig) {
    die("Error: Shop '{$shopName}' not found in configuration.");
}

// Load supplier codes for this shop
$supplierCodesPath = __DIR__ . "/../configDir/{$shopName}_suppliers.json";
if (!file_exists($supplierCodesPath)) {
    die("Error: Supplier codes file not found: {$shopName}_suppliers.json");
}

$supplierCodes = json_decode(file_get_contents($supplierCodesPath), true);
if (!$supplierCodes) {
    die("Error: Failed to parse {$shopName}_suppliers.json");
}

// Create associative array for quick lookup
$supplierCodeMap = [];
foreach ($supplierCodes as $supplier) {
    $supplierCodeMap[$supplier['SupplierName']] = $supplier['SupplierCode'];
}

// Define directories
$sourceDir = __DIR__ . '/../commercialLayer/commercial_invoice_files';
$outputDir = __DIR__ . '/../toBackOffice';

// Ensure output directory exists
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Scan for CL, PC, NP files
$allFiles = scandir($sourceDir);
$clFiles = [];
$pcFiles = [];
$npFiles = [];

// DEBUG: Show what we're looking for
echo "<h3>Debug Information:</h3>";
echo "<p><strong>Looking for date:</strong> {$processDateFull}</p>";
echo "<p><strong>Source directory:</strong> {$sourceDir}</p>";
echo "<p><strong>Total files in directory:</strong> " . count($allFiles) . "</p>";
echo "<hr>";

$matchedDateFiles = [];

foreach ($allFiles as $file) {
    if ($file === '.' || $file === '..') {
        continue;
    }

    $filePath = $sourceDir . '/' . $file;

    // Check if file matches the shop and date pattern
    // Format: OCRsanity_{Supplier}_{dd-mm-yyyy}...
    if (preg_match('/^OCRsanity_([A-Za-z]+)_(\d{2}-\d{2}-\d{4})/', $file, $matches)) {
        $fileSupplier = $matches[1];
        $fileDate = $matches[2];

        // DEBUG: Track files with matching dates
        if ($fileDate === $processDateFull) {
            $matchedDateFiles[] = $file;
        }

        // Check if date matches
        if ($fileDate !== $processDateFull) {
            continue;
        }

        // Categorize file type
        // IMPORTANT: Check PC and NP first since they contain _CL_ in their names
        // Note: Timestamp format is ddmmyyyy_hhmmss (8 digits + 6 digits)
        if (preg_match('/_PRICE-CHANGE_\d{6}_\d{6}\.xlsx$/', $file)) {
            $pcFiles[] = [
                'path' => $filePath,
                'name' => $file,
                'supplier' => $fileSupplier,
                'date' => $fileDate,
                'mtime' => filemtime($filePath)
            ];
        } elseif (preg_match('/_NEW-PRODUCTS_\d{6}_\d{6}\.xlsx$/', $file)) {
            $npFiles[] = [
                'path' => $filePath,
                'name' => $file,
                'supplier' => $fileSupplier,
                'date' => $fileDate,
                'mtime' => filemtime($filePath)
            ];
        } elseif (preg_match('/_CL_\d{8}_\d{6}\.xlsx$/', $file)) {
            // Pure CL files (no PRICE-CHANGE or NEW-PRODUCTS suffix)
            // Timestamp format: _CL_ddmmyyyy_hhmmss.xlsx
            $clFiles[] = [
                'path' => $filePath,
                'name' => $file,
                'supplier' => $fileSupplier,
                'date' => $fileDate,
                'mtime' => filemtime($filePath)
            ];
        }
    }
}

// DEBUG: Show matched files
echo "<h3>Files with matching date ({$processDateFull}):</h3>";
if (empty($matchedDateFiles)) {
    echo "<p style='color: red;'>❌ No files found with date {$processDateFull}</p>";
} else {
    echo "<ul>";
    foreach ($matchedDateFiles as $mf) {
        echo "<li>{$mf}</li>";
    }
    echo "</ul>";
}
echo "<hr>";

// Sort CL files by modification time (older first)
usort($clFiles, function($a, $b) {
    return $a['mtime'] - $b['mtime'];
});

// DEBUG: Show categorized files
echo "<h3>Categorized Files:</h3>";
echo "<p><strong>CL Files:</strong> " . count($clFiles) . "</p>";
if (!empty($clFiles)) {
    echo "<ul>";
    foreach ($clFiles as $cf) {
        echo "<li>{$cf['name']}</li>";
    }
    echo "</ul>";
}
echo "<p><strong>PC Files:</strong> " . count($pcFiles) . "</p>";
echo "<p><strong>NP Files:</strong> " . count($npFiles) . "</p>";
echo "<hr>";

// Check if we have CL files
if (empty($clFiles)) {
    die("Error: No CL files found for shop '{$shopName}' and date '{$processDateFull}'");
}

echo "<h2>Files Found:</h2>";
echo "<p><strong>CL Files:</strong> " . count($clFiles) . "</p>";
echo "<p><strong>PC Files:</strong> " . count($pcFiles) . "</p>";
echo "<p><strong>NP Files:</strong> " . count($npFiles) . "</p>";
echo "<hr>";

// ============================================
// STEP 1: Generate Invoice_list file
// ============================================

$invoiceListFile = $outputDir . "/invoice_list_{$processDateShort}.xlsx";
$invoiceList = new Spreadsheet();
$invoiceSheet = $invoiceList->getActiveSheet();

// Set headers
$invoiceSheet->setCellValue('A1', 'supplier');
$invoiceSheet->setCellValue('B1', 'date');
$invoiceSheet->setCellValue('C1', 'supplierCode');
$invoiceSheet->setCellValue('D1', 'invoiceNum');
$invoiceSheet->setCellValue('E1', 'invoiceSum');
$invoiceSheet->setCellValue('F1', 'remark');

$rowNum = 2;

foreach ($clFiles as $clFile) {
    $supplierName = $clFile['supplier'];

    // Get supplier code
    $supplierCode = isset($supplierCodeMap[$supplierName]) ? $supplierCodeMap[$supplierName] : 'UNKNOWN';

    // Load CL file
    $clSpreadsheet = IOFactory::load($clFile['path']);
    $clSheet = $clSpreadsheet->getActiveSheet();

    // Extract values from CL file
    $cellB1 = $clSheet->getCell('B1')->getValue(); // Invoice Number
    $cellB2 = $clSheet->getCell('B2')->getValue(); // Supplier Name
    $cellB3 = $clSheet->getCell('B3')->getValue(); // Invoice Date
    $cellB4 = $clSheet->getCell('B4')->getValue(); // Invoice Total
    $cellB5 = $clSheet->getCell('B5')->getValue(); // Remark

    // Convert date to dd.mm.yyyy format (standard format)
    $invoiceDateFormatted = $cellB3;

    // Handle different date formats and convert to dd.mm.yyyy
    if (preg_match('/^(\d{2})[-.](\d{2})[-.](\d{4})$/', $cellB3, $dateMatches)) {
        // Format: dd-mm-yyyy or dd.mm.yyyy (4-digit year)
        $invoiceDateFormatted = $dateMatches[1] . '.' . $dateMatches[2] . '.' . $dateMatches[3];
    } elseif (preg_match('/^(\d{2})[-.](\d{2})[-.](\d{2})$/', $cellB3, $dateMatches)) {
        // Format: dd-mm-yy or dd.mm.yy (2-digit year)
        // Convert 2-digit year to 4-digit year
        $year = intval($dateMatches[3]);
        $fullYear = ($year >= 0 && $year <= 50) ? (2000 + $year) : (1900 + $year);
        $invoiceDateFormatted = $dateMatches[1] . '.' . $dateMatches[2] . '.' . $fullYear;
    }

    // Write to invoice list
    $invoiceSheet->setCellValue("A{$rowNum}", $cellB2); // Supplier
    $invoiceSheet->setCellValue("B{$rowNum}", $invoiceDateFormatted); // Date in dd.mm.yyyy format
    $invoiceSheet->setCellValue("C{$rowNum}", $supplierCode); // Supplier Code
    $invoiceSheet->setCellValueExplicit("D{$rowNum}", $cellB1, DataType::TYPE_STRING); // Invoice Number
    $invoiceSheet->setCellValue("E{$rowNum}", $cellB4); // Invoice Sum
    $invoiceSheet->setCellValue("F{$rowNum}", $cellB5); // Remark

    $rowNum++;
}

// Auto-size columns
foreach (range('A', 'F') as $col) {
    $invoiceSheet->getColumnDimension($col)->setAutoSize(true);
}

// Save invoice list
$writer = new Xlsx($invoiceList);
$writer->save($invoiceListFile);

echo "<p>✅ Invoice list created: <strong>{$invoiceListFile}</strong></p>";
echo "<p>📊 Total invoices processed: <strong>" . count($clFiles) . "</strong></p>";
echo "<hr>";

// ============================================
// STEP 2: Generate Invoice_to_upload files
// ============================================

$invoicesToUploadCount = 0;

foreach ($clFiles as $clFile) {
    // Extract invoice signature from filename
    // Format: OCRsanity_{Supplier}_{dd-mm-yyyy}_{Letter}_ddmmyy_hhmmss_CL_ddmmyy_hhmmss.xlsx
    if (preg_match('/OCRsanity_([A-Za-z]+_\d{2}-\d{2}-\d{4}_[A-Z])/', $clFile['name'], $matches)) {
        $invoiceSignature = $matches[1];
    } else {
        echo "<p>⚠️ Warning: Could not extract invoice signature from {$clFile['name']}</p>";
        continue;
    }

    // Load CL file
    $clSpreadsheet = IOFactory::load($clFile['path']);
    $clSheet = $clSpreadsheet->getActiveSheet();

    // Get highest row in CL file
    $highestRow = $clSheet->getHighestRow();

    // Create new Invoice_to_upload file
    $invoiceToUpload = new Spreadsheet();
    $uploadSheet = $invoiceToUpload->getActiveSheet();

    // Get mapping configuration
    $mapping = $shopConfig['Invoice_to_upload_HeadersMapping'];

    // Set headers and process data
    $destRow = 1;

    // Write headers (row 1)
    foreach ($mapping as $destCol => $config) {
        $header = $config[0];
        $uploadSheet->setCellValue("{$destCol}1", $header);
    }

    // Process data rows (starting from row 2 in CL file)
    $rowIndex = 1; // Row index counter starting at 1
    for ($sourceRow = 2; $sourceRow <= $highestRow; $sourceRow++) {
        $destRow = $sourceRow; // Keep same row number in destination

        foreach ($mapping as $destCol => $config) {
            $header = $config[0];
            $originCol = $config[1];
            $dataType = $config[2];

            // Handle origin column = 0 (special cases)
            if ($originCol === 0) {
                // Column A: Auto-generate row number starting from 1
                if ($destCol === 'A') {
                    $uploadSheet->setCellValue("{$destCol}{$destRow}", $rowIndex);
                }
                // Other columns with origin = 0: leave empty
                continue;
            }

            // Get value from CL file
            $cellAddress = "{$originCol}{$sourceRow}";
            $value = $clSheet->getCell($cellAddress)->getValue();

            // Apply data type formatting
            if ($dataType === 'T') {
                // Text - use explicit string type
                $uploadSheet->setCellValueExplicit("{$destCol}{$destRow}", $value, DataType::TYPE_STRING);
            } elseif ($dataType === 'D') {
                // Double - numeric with decimals
                $uploadSheet->setCellValue("{$destCol}{$destRow}", (float)$value);
                $uploadSheet->getStyle("{$destCol}{$destRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            } elseif ($dataType === 'I') {
                // Integer - whole number
                $uploadSheet->setCellValue("{$destCol}{$destRow}", (int)$value);
                $uploadSheet->getStyle("{$destCol}{$destRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            } else {
                // No specific formatting
                $uploadSheet->setCellValue("{$destCol}{$destRow}", $value);
            }
        }

        $rowIndex++; // Increment row index for next row
    }

    // Auto-size all columns
    foreach (array_keys($mapping) as $col) {
        $uploadSheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Save Invoice_to_upload file
    $invoiceToUploadFile = $outputDir . "/invoice_to_upload_{$invoiceSignature}.xlsx";

    // Delete existing file if it exists
    if (file_exists($invoiceToUploadFile)) {
        unlink($invoiceToUploadFile);
    }

    $writer = new Xlsx($invoiceToUpload);
    $writer->save($invoiceToUploadFile);

    echo "<p>✅ Invoice to upload created: <strong>invoice_to_upload_{$invoiceSignature}.xlsx</strong></p>";
    $invoicesToUploadCount++;
}

echo "<hr>";
echo "<h2>✨ BOpack Process Complete!</h2>";
echo "<p>📋 Invoice list: <strong>1 file</strong></p>";
echo "<p>📦 Invoices to upload: <strong>{$invoicesToUploadCount} files</strong></p>";
echo "<p>📁 Output directory: <strong>{$outputDir}</strong></p>";
echo "<hr>";
echo '<p><a href="bopack_start.php">← חזור למסך הפתיחה</a></p>';
?>
