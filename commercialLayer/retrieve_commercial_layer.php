<?php
// Retrieve Commercial Layer data - re-match barcodes with price list

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;

header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['filename']) || !isset($data['shop']) || !isset($data['data'])) {
    echo json_encode(['success' => false, 'error' => 'Missing filename, shop, or data']);
    exit;
}

$filename = basename($data['filename']);
$shopName = $data['shop'];
$updatedData = $data['data'];

$clFilePath = __DIR__ . '/commercial_invoice_files/' . $filename;

if (!file_exists($clFilePath)) {
    echo json_encode(['success' => false, 'error' => 'CL file not found']);
    exit;
}

try {
    // Load shops_V2.json to get price list columns configuration
    $shopsConfigPath = __DIR__ . '/../configDir/shops_V2.json';
    if (!file_exists($shopsConfigPath)) {
        throw new Exception('shops_V2.json not found');
    }

    $shopsConfig = json_decode(file_get_contents($shopsConfigPath), true);
    $shopConfig = null;

    foreach ($shopsConfig as $shop) {
        if ($shop['ShopName'] === $shopName) {
            $shopConfig = $shop;
            break;
        }
    }

    if (!$shopConfig) {
        throw new Exception("Shop configuration not found for: $shopName");
    }

    $priceListColumns = $shopConfig['PriceListColumns'];

    // Find the price list file for this shop
    $priceListDir = __DIR__ . '/price_list_files/';
    $priceListFiles = glob($priceListDir . $shopName . '*.xlsx');

    if (empty($priceListFiles)) {
        throw new Exception("No price list file found for shop: $shopName");
    }

    // Use the most recent price list file
    usort($priceListFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $priceListPath = $priceListFiles[0];

    // Load CL Excel file
    $clSpreadsheet = IOFactory::load($clFilePath);
    $clSheet = $clSpreadsheet->getActiveSheet();

    // Load Price List Excel file
    $plSpreadsheet = IOFactory::load($priceListPath);
    $plSheet = $plSpreadsheet->getActiveSheet();
    $plHighestRow = $plSheet->getHighestRow();

    // Get column letters from PriceListColumns configuration
    $plItemERPNameCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($priceListColumns['ItemERPName']);
    $plBarcodeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($priceListColumns['Barcode']);
    $plDepartmentCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($priceListColumns['Department']);
    $plCostPriceCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($priceListColumns['CostPrice']);
    $plSalesPriceCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($priceListColumns['SalesPrice']);

    // First, update the CL file with the user's edits from Handsontable
    foreach ($updatedData as $rowIndex => $rowData) {
        foreach ($rowData as $colIndex => $value) {
            $excelRow = $rowIndex + 1;
            $excelCol = $colIndex + 1;

            if ($value === null || $value === '') {
                continue;
            }

            // Column D (4) is Barcode - save as text
            if ($excelCol === 4) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($excelCol);
                $clSheet->setCellValueExplicit("{$columnLetter}{$excelRow}", $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            // Numeric columns: B, F, G, H, I, J, K, L, O (cost/price columns)
            else if (in_array($excelCol, [2, 6, 7, 8, 9, 10, 11, 12, 15])) {
                $numericValue = str_replace(',', '', $value);
                $numericValue = str_replace('%', '', $numericValue);
                if (is_numeric($numericValue)) {
                    $clSheet->setCellValueByColumnAndRow($excelCol, $excelRow, (float)$numericValue);
                } else {
                    $clSheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
                }
            }
            // Column P (16) - PriceDiff
            else if ($excelCol === 16) {
                $cleanValue = str_replace('%', '', $value);
                if (is_numeric($cleanValue)) {
                    $clSheet->setCellValueByColumnAndRow($excelCol, $excelRow, (float)$cleanValue);
                } else {
                    $clSheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
                }
            }
            else {
                $clSheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
            }
        }
    }

    // Now re-match barcodes and retrieve commercial data
    $highestRow = $clSheet->getHighestRow();
    $updatedCells = [];

    // Start from row 2 (skip header)
    for ($n = 2; $n <= $highestRow; $n++) {
        // Get barcode from column D
        $ib = $clSheet->getCell("D{$n}")->getValue();

        if (empty($ib)) {
            continue;
        }

        // Convert to string and remove any non-numeric characters
        $ib = (string)$ib;
        $ib = preg_replace('/[^0-9]/', '', $ib);

        if (empty($ib)) {
            continue;
        }

        // Search for matching barcode in Price List
        $matchFound = false;
        $matchRow = null;

        for ($m = 2; $m <= $plHighestRow; $m++) {
            $plb = $plSheet->getCell("{$plBarcodeCol}{$m}")->getValue();

            // Convert to string and remove any non-numeric characters
            $plb = (string)$plb;
            $plb = preg_replace('/[^0-9]/', '', $plb);

            $ibLen = strlen($ib);
            $plbLen = strlen($plb);

            // Case 1: Same length - exact match
            if ($ibLen === $plbLen) {
                if ($ib === $plb) {
                    $matchFound = true;
                    $matchRow = $m;
                    break;
                }
            }
            // Case 2: Different length, both longer than 6 - suffix match
            elseif ($ibLen > 6 && $plbLen > 6) {
                if (strlen($ib) < strlen($plb)) {
                    if (substr($plb, -$ibLen) === $ib) {
                        $matchFound = true;
                        $matchRow = $m;
                        break;
                    }
                } else {
                    if (substr($ib, -$plbLen) === $plb) {
                        $matchFound = true;
                        $matchRow = $m;
                        break;
                    }
                }
            }
            // Case 3: IB shorter than 6 - pad with zeros and check suffix
            elseif ($ibLen < 6) {
                $ibPadded = str_pad($ib, 6, '0', STR_PAD_LEFT);
                if (strlen($ibPadded) <= strlen($plb)) {
                    if (substr($plb, -6) === $ibPadded) {
                        $matchFound = true;
                        $matchRow = $m;
                        break;
                    }
                }
            }
        }

        if ($matchFound) {
            // Copy ItemERPName (PL -> CL column M)
            $itemERPName = $plSheet->getCell("{$plItemERPNameCol}{$matchRow}")->getValue();
            $clSheet->setCellValue("M{$n}", $itemERPName);

            // Copy Department (PL -> CL column N)
            $department = $plSheet->getCell("{$plDepartmentCol}{$matchRow}")->getValue();
            $clSheet->setCellValue("N{$n}", $department);

            // Copy CostPrice (PL -> CL column L - OriginalUnitPrice)
            $costPrice = $plSheet->getCell("{$plCostPriceCol}{$matchRow}")->getValue();
            $clSheet->setCellValue("L{$n}", $costPrice);
            $clSheet->getStyle("L{$n}")->getNumberFormat()->setFormatCode('#,##0.00');

            // Copy SalesPrice (PL -> CL column O)
            $salesPrice = $plSheet->getCell("{$plSalesPriceCol}{$matchRow}")->getValue();
            $clSheet->setCellValue("O{$n}", $salesPrice);
            $clSheet->getStyle("O{$n}")->getNumberFormat()->setFormatCode('#,##0.00');

            // Calculate price difference percentage: (K / L - 1) * 100
            $actualUnitPrice = $clSheet->getCell("K{$n}")->getValue();
            $originalUnitPrice = $costPrice;

            $priceDiffValue = null;
            $priceDiffBg = null;

            if (is_numeric($actualUnitPrice) && is_numeric($originalUnitPrice) && $originalUnitPrice != 0) {
                $priceDiff = (((float)$actualUnitPrice / (float)$originalUnitPrice) - 1) * 100;
                $priceDiff = round($priceDiff, 2);

                $clSheet->setCellValue("P{$n}", $priceDiff);
                $clSheet->getStyle("P{$n}")->getNumberFormat()->setFormatCode('0.00"%"');

                // Apply conditional formatting
                if ($priceDiff > 0) {
                    // Light red background
                    $clSheet->getStyle("P{$n}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFFCCCC');
                    $priceDiffBg = '#FFCCCC';
                } elseif ($priceDiff < 0) {
                    // Light green background
                    $clSheet->getStyle("P{$n}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFCCFFCC');
                    $priceDiffBg = '#CCFFCC';
                }

                $priceDiffValue = $priceDiff;
            }

            // Track updated cells for response
            $updatedCells[] = [
                'row' => $n - 1, // 0-indexed for Handsontable
                'col' => 12, // Column M (ItemERPName)
                'value' => $itemERPName
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 13, // Column N (Department)
                'value' => $department
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 11, // Column L (OriginalUnitPrice)
                'value' => number_format($costPrice, 2)
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 14, // Column O (SalesPrice)
                'value' => number_format($salesPrice, 2)
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 15, // Column P (PriceDiff)
                'value' => $priceDiffValue,
                'background' => $priceDiffBg
            ];
        } else {
            // No match found
            $clSheet->setCellValue("P{$n}", "Not Found");
            $clSheet->getStyle("P{$n}")->getFill()->setFillType(Fill::FILL_NONE);

            // Clear other columns
            $clSheet->setCellValue("L{$n}", "");
            $clSheet->setCellValue("M{$n}", "");
            $clSheet->setCellValue("N{$n}", "");
            $clSheet->setCellValue("O{$n}", "");

            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 11,
                'value' => ''
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 12,
                'value' => ''
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 13,
                'value' => ''
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 14,
                'value' => ''
            ];
            $updatedCells[] = [
                'row' => $n - 1,
                'col' => 15,
                'value' => 'Not Found',
                'background' => null
            ];
        }
    }

    // Save the file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($clSpreadsheet);
    $writer->save($clFilePath);

    echo json_encode([
        'success' => true,
        'message' => 'Commercial data retrieved successfully',
        'updatedCells' => $updatedCells
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
