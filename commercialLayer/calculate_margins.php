<?php
/**
 * Calculate Margins for New Products
 * Calculates DepartmentMargin and ActualMargin for each row
 */

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['npFileName']) || !isset($input['shopName'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$npFileName = $input['npFileName'];
$shopName = $input['shopName'];

try {
    // 1. Load the NP file
    $npFilePath = __DIR__ . '/commercial_invoice_files/' . $npFileName;

    if (!file_exists($npFilePath)) {
        throw new Exception('NP file not found: ' . $npFileName);
    }

    $npSpreadsheet = IOFactory::load($npFilePath);
    $npSheet = $npSpreadsheet->getActiveSheet();
    $npData = $npSheet->toArray();

    // 2. Load departments configuration
    $departmentsConfigPath = __DIR__ . '/../configDir/' . $shopName . '_Departments.json';
    if (!file_exists($departmentsConfigPath)) {
        throw new Exception('Departments configuration not found for shop: ' . $shopName);
    }

    $departmentsConfig = json_decode(file_get_contents($departmentsConfigPath), true);

    // Create a map of DepartmentName => ExpectedMarginPercentage for quick lookup
    $departmentMargins = [];
    foreach ($departmentsConfig as $dept) {
        if (isset($dept['DepartmentName']) && isset($dept['ExpectedMarginPercentage'])) {
            $departmentMargins[$dept['DepartmentName']] = $dept['ExpectedMarginPercentage'];
        }
    }

    // 3. Get column indices
    $headers = $npData[0];
    $colDepartmentName = array_search('DepartmentName', $headers);
    $colDepartmentMargin = array_search('DepartmentMargin', $headers);
    $colActualUnitPrice = array_search('ActualUnitPrice', $headers);
    $colSalePrice = array_search('SalePrice', $headers);
    $colActualMargin = array_search('ActualMargin', $headers);
    $colBarcode = array_search('Barcode', $headers);

    if ($colDepartmentName === false || $colDepartmentMargin === false ||
        $colActualUnitPrice === false || $colSalePrice === false ||
        $colActualMargin === false) {
        throw new Exception('Required columns not found in NP file');
    }

    // 4. Process each row (skip header row)
    $processedRows = 0;
    for ($i = 1; $i < count($npData); $i++) {
        $row = $npData[$i];
        $rowNum = $i + 1; // Excel row number (1-based)

        // Skip rows without barcode (empty rows)
        $barcode = $row[$colBarcode] ?? '';
        if (empty($barcode)) {
            continue;
        }

        $departmentName = $row[$colDepartmentName] ?? '';
        $actualUnitPrice = $row[$colActualUnitPrice] ?? 0;
        $salePrice = $row[$colSalePrice] ?? 0;

        // Calculate DepartmentMargin
        $departmentMarginValue = '';
        if (empty($departmentName)) {
            $departmentMarginValue = 'Department Name is missing';
        } else {
            if (isset($departmentMargins[$departmentName])) {
                $marginPercentage = $departmentMargins[$departmentName];
                // Convert to percentage format (e.g., 0.35 -> 35%)
                $departmentMarginValue = round($marginPercentage * 100, 2) . '%';
            } else {
                $departmentMarginValue = 'Department not found';
            }
        }

        // Calculate ActualMargin
        $actualMarginValue = '';
        if (empty($salePrice) || !is_numeric($salePrice) || $salePrice <= 0) {
            $actualMarginValue = 'Sale Price is incorrect';
        } else {
            // Formula: ((SalePrice/1.18) - ActualUnitPrice) / (SalePrice/1.18)
            $salePriceExcludingVAT = $salePrice / 1.18;
            $actualUnitPriceNum = is_numeric($actualUnitPrice) ? (float)$actualUnitPrice : 0;

            $marginValue = ($salePriceExcludingVAT - $actualUnitPriceNum) / $salePriceExcludingVAT;
            // Convert to percentage format
            $actualMarginValue = round($marginValue * 100, 2) . '%';
        }

        // Update the spreadsheet
        $npSheet->setCellValue(chr(65 + $colDepartmentMargin) . $rowNum, $departmentMarginValue);
        $npSheet->setCellValue(chr(65 + $colActualMargin) . $rowNum, $actualMarginValue);

        // Remove orange border from the calculated cells
        $npSheet->getStyle(chr(65 + $colDepartmentMargin) . $rowNum)->getBorders()->getOutline()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
        $npSheet->getStyle(chr(65 + $colActualMargin) . $rowNum)->getBorders()->getOutline()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);

        $processedRows++;
    }

    // 5. Save the updated NP file
    $npWriter = IOFactory::createWriter($npSpreadsheet, 'Xlsx');
    $npWriter->save($npFilePath);

    // 6. Return success response
    echo json_encode([
        'success' => true,
        'processedRows' => $processedRows,
        'message' => 'Margins calculated successfully for ' . $processedRows . ' rows'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
