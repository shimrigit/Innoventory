<?php
/**
 * Generate New Products (NP) File
 * Checks CL file for new products (rows with "Not Found" in PriceDiff column)
 * and creates NP file if new products exist
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['clFileName']) || !isset($input['shopName'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$clFileName = $input['clFileName'];
$shopName = $input['shopName'];

try {
    // 1. Load the CL file
    $clFilePath = __DIR__ . '/commercial_invoice_files/' . $clFileName;

    if (!file_exists($clFilePath)) {
        throw new Exception('CL file not found: ' . $clFileName);
    }

    $clSpreadsheet = IOFactory::load($clFilePath);
    $clSheet = $clSpreadsheet->getActiveSheet();
    $clData = $clSheet->toArray();

    // 2. Check if there are new products (rows with "Not Found" in column P - PriceDiff)
    $headers = $clData[0];
    $priceDiffColIdx = array_search('PriceDiff', $headers);

    if ($priceDiffColIdx === false) {
        throw new Exception('PriceDiff column not found in CL file');
    }

    $newProductRows = [];
    for ($i = 1; $i < count($clData); $i++) { // Start from row 2 (index 1)
        $priceDiff = $clData[$i][$priceDiffColIdx] ?? '';
        if (stripos($priceDiff, 'Not Found') !== false) {
            $newProductRows[] = $i;
        }
    }

    // 3. If no new products, return message and end
    if (empty($newProductRows)) {
        echo json_encode([
            'success' => true,
            'hasNewProducts' => false,
            'message' => 'No new products found. Commercial Layer process completed.',
            'newProductCount' => 0
        ]);
        exit;
    }

    // 4. Extract InvoiceIdentifier and SupplierName from CL file name
    // CL file naming: "OCRsanity_XXXX_dd-mm-yyyy Z_ddmmyy_hhmmss_CL_ddmmyy_hhmmss"
    // InvoiceIdentifier: "XXXX_dd-mm-yyyy Z" or "XXXX_dd-mm-yyyy" if Z is empty (WITHOUT timestamp)

    if (!preg_match('/^OCRsanity_(.+?)_CL_/', $clFileName, $matches)) {
        throw new Exception('Invalid CL filename format');
    }

    $fullMatch = $matches[1]; // e.g., "Gad_10-11-2025_10112025_110745"

    // Remove the timestamp part (ddmmyy_hhmmss) from the end
    // Pattern: remove "_ddmmyy_hhmmss" from the end
    $invoiceIdentifier = preg_replace('/_\d{8}_\d{6}$/', '', $fullMatch);
    // Result: "Gad_10-11-2025" or "Gad_10-11-2025 A" if there's a letter

    // Extract SupplierName (first part before first underscore)
    $parts = explode('_', $invoiceIdentifier);
    $supplierName = $parts[0];

    // 5. Load suppliers.json to get SupplierHebrewName
    $suppliersConfigPath = __DIR__ . '/../suppliers.json';
    if (!file_exists($suppliersConfigPath)) {
        throw new Exception('suppliers.json not found');
    }

    $suppliersConfig = json_decode(file_get_contents($suppliersConfigPath), true);
    $supplierHebrewName = '';

    foreach ($suppliersConfig as $supplier) {
        if ($supplier['supplierName'] === $supplierName) {
            $supplierHebrewName = $supplier['hebrewName'] ?? $supplierName;
            break;
        }
    }

    if (empty($supplierHebrewName)) {
        $supplierHebrewName = $supplierName; // Fallback to English name
    }

    // 6. Create NP file name with timestamp
    // Format: ddmmyy_hhmmss (e.g., 231125_120000)
    $timestamp = date('dmy_His');

    // Remove .xlsx extension from CL filename, add suffix, then add extension back
    $clFileNameWithoutExt = pathinfo($clFileName, PATHINFO_FILENAME);
    $npFileName = $clFileNameWithoutExt . '_NEW-PRODUCTS_' . $timestamp . '.xlsx';
    $npFilePath = __DIR__ . '/commercial_invoice_files/' . $npFileName;

    // 7. Create NP spreadsheet
    $npSpreadsheet = new Spreadsheet();
    $npSheet = $npSpreadsheet->getActiveSheet();

    // 8. Copy columns A and B from CL file (ALL rows)
    for ($i = 0; $i < count($clData); $i++) {
        $rowNum = $i + 1; // Excel row number (1-based)
        $npSheet->setCellValue('A' . $rowNum, $clData[$i][0] ?? '');
        $npSheet->setCellValue('B' . $rowNum, $clData[$i][1] ?? '');
    }

    // 9. Set headers for NP file (row 1, columns C onwards)
    $npSheet->setCellValue('C1', 'Original Index');
    $npSheet->setCellValue('D1', 'Barcode');
    $npSheet->setCellValue('E1', 'ItemName');
    $npSheet->setCellValue('F1', 'ItemERPName');
    $npSheet->setCellValue('G1', 'ActualUnitPrice');
    $npSheet->setCellValue('H1', 'Supplier');
    $npSheet->setCellValue('I1', 'DepartmentName');
    $npSheet->setCellValue('J1', 'DepartmentMargin');
    $npSheet->setCellValue('K1', 'SalePrice');
    $npSheet->setCellValue('L1', 'ActualMargin');
    $npSheet->setCellValue('M1', 'InvoiceIdentifier');

    // 10. Get column indices from CL file
    $colIndex = array_search('Index', $headers); // The CL file has "Index" column, not "Original Index"
    $colBarcode = array_search('Barcode', $headers);
    $colItemName = array_search('ItemName', $headers);
    $colActualUnitPrice = array_search('ActualUnitPrice', $headers);

    // 11. Populate NP file with new product rows (columns C onwards only)
    // Important: Populate sequentially starting from row 2 in NP file
    // NOTE: Columns A and B remain as copied from ALL CL rows (unchanged)
    $npRowNum = 2; // Start from row 2 for sequential population (columns C onwards only)

    foreach ($newProductRows as $clRowIdx) {
        $clRow = $clData[$clRowIdx];

        // Column C - Original Index (copied from "Index" column in CL file)
        $npSheet->setCellValue('C' . $npRowNum, $clRow[$colIndex] ?? '');

        // Column D - Barcode
        $npSheet->setCellValue('D' . $npRowNum, $clRow[$colBarcode] ?? '');

        // Column E - ItemName
        $npSheet->setCellValue('E' . $npRowNum, $clRow[$colItemName] ?? '');

        // Column F - ItemERPName (empty for now - user will fill)
        $npSheet->setCellValue('F' . $npRowNum, '');

        // Column G - ActualUnitPrice
        $npSheet->setCellValue('G' . $npRowNum, $clRow[$colActualUnitPrice] ?? '');

        // Column H - Supplier (SupplierHebrewName)
        $npSheet->setCellValue('H' . $npRowNum, $supplierHebrewName);

        // Column I - DepartmentName (empty for now - user will select from dropdown)
        $npSheet->setCellValue('I' . $npRowNum, '');

        // Column J - DepartmentMargin (placeholder with orange border)
        $npSheet->setCellValue('J' . $npRowNum, 'To be calculated');
        $npSheet->getStyle('J' . $npRowNum)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THICK)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFF9800')); // Orange

        // Column K - SalePrice (empty for now - user will fill)
        $npSheet->setCellValue('K' . $npRowNum, '');

        // Column L - ActualMargin (placeholder with orange border)
        $npSheet->setCellValue('L' . $npRowNum, 'To be calculated');
        $npSheet->getStyle('L' . $npRowNum)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THICK)
            ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFF9800')); // Orange

        // Column M - InvoiceIdentifier
        $npSheet->setCellValue('M' . $npRowNum, $invoiceIdentifier);

        // Move to next row for next new product
        $npRowNum++;
    }

    // 12. Save NP file
    $npWriter = new Xlsx($npSpreadsheet);
    $npWriter->save($npFilePath);

    // 13. Store NP file info in session
    $_SESSION['npFileName'] = $npFileName;
    $_SESSION['shopName'] = $shopName;
    $_SESSION['clFileName'] = $clFileName;

    // 14. Return success response
    echo json_encode([
        'success' => true,
        'hasNewProducts' => true,
        'npFileName' => $npFileName,
        'newProductCount' => count($newProductRows),
        'supplierName' => $supplierName,
        'supplierHebrewName' => $supplierHebrewName,
        'invoiceIdentifier' => $invoiceIdentifier,
        'redirectUrl' => 'verify_new_products.php?npFile=' . urlencode($npFileName) . '&shop=' . urlencode($shopName)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
