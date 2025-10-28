<?php
// Re-verify OCR Sanity - Clear backgrounds and re-run verification

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['filename']) || !isset($data['data'])) {
    echo json_encode(['success' => false, 'error' => 'Missing filename or data']);
    exit;
}

$filename = basename($data['filename']);
$updatedData = $data['data'];

$filePath = __DIR__ . '/sanity_files/' . $filename;

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

try {
    // Load existing Excel file
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Get highest row
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    // Step 1: Clear all cell backgrounds and borders (columns A-J, from row 2 onwards)
    for ($row = 2; $row <= $highestRow; $row++) {
        foreach (range('A', 'J') as $column) {
            $cell = $sheet->getCell("{$column}{$row}");
            // Clear background
            $cell->getStyle()->getFill()->setFillType(Fill::FILL_NONE);
            // Clear borders
            $cell->getStyle()->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE);
        }
    }

    // Step 2: Update cells with new data from Handsontable
    foreach ($updatedData as $rowIndex => $rowData) {
        foreach ($rowData as $colIndex => $value) {
            $excelRow = $rowIndex + 1;
            $excelCol = $colIndex + 1;

            // Skip empty values
            if ($value === null || $value === '') {
                continue;
            }

            // Column D (4) is Barcode - save as text to prevent scientific notation
            if ($excelCol === 4) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($excelCol);
                $sheet->setCellValueExplicit("{$columnLetter}{$excelRow}", $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            // Columns with numeric data - need to handle formatted strings (e.g., "3,172.04")
            else if (in_array($excelCol, [2, 6, 7, 8, 9, 10])) { // B, F, G, H, I, J
                // Remove thousand separators and convert to float
                $numericValue = str_replace(',', '', $value);
                if (is_numeric($numericValue)) {
                    $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, (float)$numericValue);
                } else {
                    $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
                }
            }
            else {
                $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
            }
        }
    }

    // Step 3: Get sanity method from metadata sheet (or default to Simple)
    $sanityMethod = 'Simple';
    $metaSheetIndex = $spreadsheet->getSheetByName('_Metadata');
    if ($metaSheetIndex !== null) {
        $sanityMethod = $metaSheetIndex->getCell('B2')->getValue();
        if (empty($sanityMethod)) {
            $sanityMethod = 'Simple';
        }
    }

    // Apply sanity verification based on stored method
    $totalDifference = applySanityVerification($sheet, $sanityMethod);

    // Update metadata sheet with new total difference
    if ($metaSheetIndex !== null) {
        $metaSheetIndex->getCell('B1')->setValue($totalDifference);
    }

    // Step 4: Save the file
    $writer = new Xlsx($spreadsheet);
    $writer->save($filePath);

    // Step 5: Read back the cell styles to return to client
    $cellStyles = [];
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    for ($row = 1; $row <= $highestRow; $row++) {
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, $row);

            $styleData = [
                'row' => $row - 1, // 0-indexed for Handsontable
                'col' => $col - 1
            ];
            $hasStyle = false;

            // Get cell background color
            $fill = $cell->getStyle()->getFill();
            $fillType = $fill->getFillType();
            if ($fillType !== Fill::FILL_NONE) {
                $bgColor = $fill->getStartColor()->getARGB();
                // Remove FF prefix if present and convert to hex
                if (strlen($bgColor) === 8 && substr($bgColor, 0, 2) === 'FF') {
                    $bgColor = '#' . substr($bgColor, 2);
                } else {
                    $bgColor = '#' . $bgColor;
                }
                $styleData['backgroundColor'] = $bgColor;
                $hasStyle = true;
            }

            // Get cell border style (check if any border is thick/bold)
            $borders = $cell->getStyle()->getBorders();
            if ($borders->getTop()->getBorderStyle() === Border::BORDER_THICK ||
                $borders->getBottom()->getBorderStyle() === Border::BORDER_THICK ||
                $borders->getLeft()->getBorderStyle() === Border::BORDER_THICK ||
                $borders->getRight()->getBorderStyle() === Border::BORDER_THICK) {
                $styleData['boldBorder'] = true;
                $hasStyle = true;
            }

            if ($hasStyle) {
                $cellStyles[] = $styleData;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Re-verification completed',
        'cellStyles' => $cellStyles,
        'totalDifference' => $totalDifference
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Apply sanity verification to the OCRsanity spreadsheet
 */
function applySanityVerification($sheet, $method) {
    $highestRow = $sheet->getHighestRow();

    $totalDifference = 0;

    switch ($method) {
        case 'Simple':
            applyDataTypeVerification($sheet, $highestRow);
            applyBarcodeSanityVerification($sheet, $highestRow);
            break;

        case 'LineTotal':
            applyDataTypeVerification($sheet, $highestRow);
            applyBarcodeSanityVerification($sheet, $highestRow);
            applyLineSumVerification($sheet, $highestRow);
            $totalDifference = applyTotalSumVerification($sheet, $highestRow);
            break;

        case 'Discount1':
            applyDataTypeVerification($sheet, $highestRow);
            applyBarcodeSanityVerification($sheet, $highestRow);
            applyDiscount1CalcVerification($sheet, $highestRow);
            $totalDifference = applyTotalSumVerification($sheet, $highestRow);
            break;
    }

    return $totalDifference;
}

/**
 * DataType Verification: Check columns F-J for numeric values
 */
function applyDataTypeVerification($sheet, $highestRow) {
    $columnsToCheck = ['F', 'G', 'H', 'I', 'J'];

    for ($row = 2; $row <= $highestRow; $row++) {
        foreach ($columnsToCheck as $column) {
            $cell = $sheet->getCell("{$column}{$row}");
            $value = $cell->getValue();

            if ($value === null || $value === '') {
                continue;
            }

            if (!is_numeric($value)) {
                $cell->getStyle()->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE6CCE6'); // Light purple
            }
        }
    }
}

/**
 * BarcodeSanity Verification: Check column D for valid barcodes
 */
function applyBarcodeSanityVerification($sheet, $highestRow) {
    for ($row = 2; $row <= $highestRow; $row++) {
        $cell = $sheet->getCell("D{$row}");
        $value = $cell->getValue();

        if ($value === null || $value === '') {
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFFCC'); // Light yellow
            continue;
        }

        $valueStr = (string)$value;

        if (!ctype_digit($valueStr)) {
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFFCC'); // Light yellow
            continue;
        }

        if (strlen($valueStr) > 13) {
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFFFCC'); // Light yellow
        }
    }
}

/**
 * LineSum Verification: Check if Qty * UnitPrice = LineTotal
 */
function applyLineSumVerification($sheet, $highestRow) {
    for ($row = 2; $row <= $highestRow; $row++) {
        $qtyCell = $sheet->getCell("F{$row}");
        $unitPriceCell = $sheet->getCell("G{$row}");
        $lineTotalCell = $sheet->getCell("H{$row}");

        $qty = $qtyCell->getValue();
        $unitPrice = $unitPriceCell->getValue();
        $lineTotal = $lineTotalCell->getValue();

        if (($qty === null || $qty === '') && ($unitPrice === null || $unitPrice === '') && ($lineTotal === null || $lineTotal === '')) {
            continue;
        }

        if (!is_numeric($qty) || !is_numeric($unitPrice)) {
            $lineTotalCell->getStyle()->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
            continue;
        }

        $expectedTotal = round((float)$qty * (float)$unitPrice, 2);
        $actualTotal = round((float)$lineTotal, 2);

        if (abs($expectedTotal - $actualTotal) > 0.01) {
            $lineTotalCell->getStyle()->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        }
    }
}

/**
 * Discount1Calc Verification: Check if Qty × UnitPrice × (1 - Discount1/100) = LineTotal
 * Formula: F × G × (1 - I/100) = H
 * Invalid rows get bold border on cell H
 */
function applyDiscount1CalcVerification($sheet, $highestRow) {
    for ($row = 2; $row <= $highestRow; $row++) {
        $qtyCell = $sheet->getCell("F{$row}");
        $unitPriceCell = $sheet->getCell("G{$row}");
        $lineTotalCell = $sheet->getCell("H{$row}");
        $discount1Cell = $sheet->getCell("I{$row}");

        $qty = $qtyCell->getValue();
        $unitPrice = $unitPriceCell->getValue();
        $lineTotal = $lineTotalCell->getValue();
        $discount1 = $discount1Cell->getValue();

        // Skip empty rows
        if (($qty === null || $qty === '') && ($unitPrice === null || $unitPrice === '') && ($lineTotal === null || $lineTotal === '')) {
            continue;
        }

        // Check if F, G, and I are numeric
        if (!is_numeric($qty) || !is_numeric($unitPrice) || !is_numeric($discount1)) {
            // Set bold border on H cell explicitly
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK);
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getRight()->setBorderStyle(Border::BORDER_THICK);
            continue;
        }

        // Calculate expected line total with discount: Qty × UnitPrice × (1 - Discount1/100)
        $expectedTotal = round((float)$qty * (float)$unitPrice * (1 - (float)$discount1 / 100), 2);
        $actualTotal = round((float)$lineTotal, 2);

        // Compare with tolerance for floating point
        if (abs($expectedTotal - $actualTotal) > 0.01) {
            // Set bold border on H cell explicitly
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THICK);
            $sheet->getCell("H{$row}")->getStyle()->getBorders()->getRight()->setBorderStyle(Border::BORDER_THICK);
        }
    }
}

/**
 * TotalSum Verification: Check if sum of column H equals B4
 */
function applyTotalSumVerification($sheet, $highestRow) {
    $columnHSum = 0;
    $debugValues = []; // For debugging

    for ($row = 2; $row <= $highestRow; $row++) {
        $cell = $sheet->getCell("H{$row}");
        $value = $cell->getValue();

        if ($value !== null && $value !== '' && is_numeric($value)) {
            $columnHSum += (float)$value;
            $debugValues[] = "Row {$row}: " . (float)$value; // Debug
        }
    }

    $columnHSum = round($columnHSum, 2);
    $invoiceTotal = $sheet->getCell('B4')->getValue();
    $invoiceTotal = round((float)$invoiceTotal, 2);

    // Calculate signed difference: B4 - sum of column H
    $difference = $invoiceTotal - $columnHSum;
    $difference = round($difference, 2);

    // Debug logging
    error_log("TotalSum Debug - B4: {$invoiceTotal}, Sum(H): {$columnHSum}, Difference: {$difference}");
    error_log("Column H values: " . implode(", ", $debugValues));

    return $difference;
}
?>
