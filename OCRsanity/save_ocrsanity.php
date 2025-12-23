<?php
// Save updated OCR Sanity data back to Excel file

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

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

    // Clear existing data (but keep formatting)
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    // Update with new data
    foreach ($updatedData as $rowIndex => $rowData) {
        foreach ($rowData as $colIndex => $value) {
            $excelRow = $rowIndex + 1;
            $excelCol = $colIndex + 1;

            // Column D (4) is Barcode - save as text to prevent scientific notation
            if ($excelCol === 4 && !empty($value)) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($excelCol);
                $sheet->setCellValueExplicit("{$columnLetter}{$excelRow}", $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValueByColumnAndRow($excelCol, $excelRow, $value);
            }
        }
    }

    // Save the file
    $writer = new Xlsx($spreadsheet);
    $writer->save($filePath);

    // Delete temporary PDF file if exists (unless skipPdfDelete flag is set)
    // The temp PDF has same name as Excel file but with .pdf extension
    $skipPdfDelete = isset($data['skipPdfDelete']) && $data['skipPdfDelete'] === true;

    if (!$skipPdfDelete) {
        $baseFileName = str_replace('.xlsx', '', $filename);
        $tempPdfPath = __DIR__ . '/sanity_files/temp_' . $baseFileName . '.xlsx.pdf';

        if (file_exists($tempPdfPath)) {
            unlink($tempPdfPath);
        }
    }

    echo json_encode(['success' => true, 'message' => 'File saved successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
