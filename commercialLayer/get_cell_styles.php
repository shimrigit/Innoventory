<?php
/**
 * Get Cell Styles from Excel File
 * Returns cell styles (background colors and bold borders) as JSON
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// Get filename parameter
$filename = $_GET['filename'] ?? '';

if (empty($filename)) {
    echo json_encode(['success' => false, 'error' => 'Missing filename parameter']);
    exit;
}

$filePath = __DIR__ . '/commercial_invoice_files/' . basename($filename);

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

try {
    // Load Excel file
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

    // Collect cell styles (background colors and borders)
    $cellStyles = [];

    for ($row = 1; $row <= $highestRow; $row++) {
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, $row);

            $styleData = [
                'row' => $row - 1,
                'col' => $col - 1
            ];
            $hasStyle = false;

            // Get background color
            $fill = $cell->getStyle()->getFill();
            $fillType = $fill->getFillType();
            if ($fillType !== \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE) {
                $bgColor = $fill->getStartColor()->getARGB();
                if (strlen($bgColor) === 8 && substr($bgColor, 0, 2) === 'FF') {
                    $bgColor = '#' . substr($bgColor, 2);
                } else {
                    $bgColor = '#' . $bgColor;
                }
                $styleData['backgroundColor'] = $bgColor;
                $hasStyle = true;
            }

            // Get border styles - check if any border is THICK (bold)
            $borders = $cell->getStyle()->getBorders();
            if ($borders->getTop()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK ||
                $borders->getBottom()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK ||
                $borders->getLeft()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK ||
                $borders->getRight()->getBorderStyle() === \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK) {
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
        'styles' => $cellStyles
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
