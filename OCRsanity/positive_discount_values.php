<?php
/**
 * Convert negative discount values to positive in Discount1 and Discount2 columns
 * This is triggered manually by the "Positive discount values" button
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

    // Define discount columns: I (Discount1), J (Discount2)
    $discountColumns = ['I', 'J'];
    $convertedCount = 0;

    for ($row = 2; $row <= $highestRow; $row++) {
        foreach ($discountColumns as $column) {
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

            // Convert to string and check if it starts with minus sign
            $valueStr = (string)$value;

            // If value starts with minus sign, remove it
            if (strpos($valueStr, '-') === 0) {
                $positiveValue = ltrim($valueStr, '-');
                $cell->setValue($positiveValue);
                $convertedCount++;
            }
        }
    }

    // Save the file
    $writer = new Xlsx($spreadsheet);
    $writer->save($fullPath);

    echo json_encode([
        'success' => true,
        'message' => "המרה הושלמה בהצלחה! $convertedCount ערכי הנחה הומרו לחיוביים.",
        'convertedCount' => $convertedCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה: ' . $e->getMessage()
    ]);
}
