<?php
// Re-verify OCR Sanity - Clear backgrounds and re-run verification

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

    // Step 1: Clear all cell backgrounds (columns A-J, from row 2 onwards)
    for ($row = 2; $row <= $highestRow; $row++) {
        foreach (range('A', 'J') as $column) {
            $cell = $sheet->getCell("{$column}{$row}");
            $cell->getStyle()->getFill()->setFillType(Fill::FILL_NONE);
        }
    }

    // Step 2: Update cells with new data from Handsontable
    foreach ($updatedData as $rowIndex => $rowData) {
        foreach ($rowData as $colIndex => $value) {
            $excelRow = $rowIndex + 1;
            $excelCol = $colIndex + 1;

            // Column D (4) is Barcode - save as text to prevent scientific notation
            if ($excelCol === 4 && !empty($value)) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($excelCol);
                $sheet->setCellValueExplicit("{$columnLetter}{$excelRow}", $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
            }
        }
    }

    // Step 3: Apply "Simple" sanity verification
    applySanityVerification($sheet, 'Simple');

    // Step 4: Save the file
    $writer = new Xlsx($spreadsheet);
    $writer->save($filePath);

    // Step 5: Read back the cell styles to return to client
    $cellStyles = [];
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    for ($row = 1; $row <= $highestRow; $row++) {
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, $row);

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
                $cellStyles[] = [
                    'row' => $row - 1, // 0-indexed for Handsontable
                    'col' => $col - 1,
                    'backgroundColor' => $bgColor
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Re-verification completed',
        'cellStyles' => $cellStyles
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Apply sanity verification to the OCRsanity spreadsheet
 */
function applySanityVerification($sheet, $method) {
    $highestRow = $sheet->getHighestDataRow();
    if (!$highestRow) {
        $highestRow = $sheet->getHighestRow();
    }

    switch ($method) {
        case 'Simple':
            applyDataTypeVerification($sheet, $highestRow);
            applyBarcodeSanityVerification($sheet, $highestRow);
            break;
    }
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
?>
