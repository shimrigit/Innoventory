<?php
/**
 * Clean non-numeric characters from numeric columns
 * This is triggered manually by the "Clean non-numeric" button
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/json');

try {
    if (!isset($_POST['file'])) {
        throw new Exception('No file specified');
    }

    $file = basename($_POST['file']); // Get just the filename for security
    $fullPath = __DIR__ . '/sanity_files/' . $file;

    if (!file_exists($fullPath)) {
        throw new Exception('File not found: ' . $file);
    }

    // Load the spreadsheet
    $spreadsheet = IOFactory::load($fullPath);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();

    // Define columns to clean: D (Barcode), F (Qty), G (UnitPrice), H (LineTotal), I (Discount1), J (Discount2)
    $numericColumns = ['D', 'F', 'G', 'H', 'I', 'J'];
    $cleanedCount = 0;

    for ($row = 2; $row <= $highestRow; $row++) {
        foreach ($numericColumns as $column) {
            // Check if column exists in the sheet (compare column letters)
            if (\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column) >
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn)) {
                continue; // Skip columns that don't exist
            }

            // Use cellExists to check if cell has any value
            if (!$sheet->cellExists("{$column}{$row}")) {
                continue;
            }

            $cell = $sheet->getCell("{$column}{$row}");
            $value = $cell->getValue();

            // Skip empty cells
            if ($value === null || $value === '') {
                continue;
            }

            // Convert to string
            $valueStr = (string)$value;

            // Remove all non-numeric characters except minus (-) and dot (.)
            // This regex keeps: digits (0-9), minus sign (-), and decimal point (.)
            $cleanedValue = preg_replace('/[^0-9.\-]/', '', $valueStr);

            // Only update if the value changed
            if ($cleanedValue !== $valueStr) {
                $cell->setValue($cleanedValue);
                $cleanedCount++;
            }
        }
    }

    // Save the file
    $writer = new Xlsx($spreadsheet);
    $writer->save($fullPath);

    echo json_encode([
        'success' => true,
        'message' => "ניקוי הושלם בהצלחה! $cleanedCount תאים נוקו.",
        'cleanedCount' => $cleanedCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה: ' . $e->getMessage()
    ]);
}
