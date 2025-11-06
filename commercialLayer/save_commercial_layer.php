<?php
// Save Commercial Layer file after verification

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

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
$shopName = $data['shop'] ?? '';

$filePath = __DIR__ . '/commercial_invoice_files/' . $filename;

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

try {
    // Load existing Excel file
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Get highest row and column
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    // Update cells with new data from Handsontable
    foreach ($updatedData as $rowIndex => $rowData) {
        foreach ($rowData as $colIndex => $value) {
            $excelRow = $rowIndex + 1;
            $excelCol = $colIndex + 1;

            // Skip empty values
            if ($value === null || $value === '') {
                continue;
            }

            // Column D (4) is Barcode - save as text
            if ($excelCol === 4) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($excelCol);
                $sheet->setCellValueExplicit("{$columnLetter}{$excelRow}", $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            // Numeric columns: B, F, G, H, I, J, K, L, O (cost/price columns)
            else if (in_array($excelCol, [2, 6, 7, 8, 9, 10, 11, 12, 15])) {
                // Remove thousand separators and convert to float
                $numericValue = str_replace(',', '', $value);
                // Remove % sign if present
                $numericValue = str_replace('%', '', $numericValue);
                if (is_numeric($numericValue)) {
                    $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, (float)$numericValue);
                } else {
                    $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
                }
            }
            // Column P (16) - PriceDiff - special handling for percentage or "Not Found"
            else if ($excelCol === 16) {
                $cleanValue = str_replace('%', '', $value);
                if (is_numeric($cleanValue)) {
                    $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, (float)$cleanValue);
                } else {
                    // "Not Found" or other text
                    $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
                }
            }
            else {
                $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
            }
        }
    }

    // Save the file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($filePath);

    // Clean up temp PDF file
    $tempPdfName = 'temp_' . $filename . '.pdf';
    $tempPdfPath = __DIR__ . '/commercial_invoice_files/' . $tempPdfName;
    if (file_exists($tempPdfPath)) {
        unlink($tempPdfPath);
    }

    echo json_encode([
        'success' => true,
        'message' => 'CL file saved successfully',
        'filename' => $filename
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
