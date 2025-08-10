<?php
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

function rgbToArgb($rgb) {
    if (preg_match('/^rgb\s*\(\s*(\d+),\s*(\d+),\s*(\d+)\s*\)$/', $rgb, $matches)) {
        $r = str_pad(dechex($matches[1]), 2, '0', STR_PAD_LEFT);
        $g = str_pad(dechex($matches[2]), 2, '0', STR_PAD_LEFT);
        $b = str_pad(dechex($matches[3]), 2, '0', STR_PAD_LEFT);
        return 'FF' . $r . $g . $b; // Default alpha value of 'FF' for full opacity
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $content = $data['content'];
    $xlsxFile = $data['xlsxFile'];

    // Start output buffering
    ob_start();

    $contentSTR = json_encode($content);

    //file_put_contents('debug.txt', "Raw Input:\n" . $contentSTR . "\n\n", FILE_APPEND);

    // End buffering and clean the output
    ob_end_clean();

    try {
        // Create a new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Update the spreadsheet with the new content
        foreach ($content as $rowIndex => $row) {
            foreach ($row as $colIndex => $cellData) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($colIndex + 1) . ($rowIndex + 1);
                $cell = $sheet->getCell($cellCoordinate);
                $cell->setValue($cellData['value']);
                ob_start();
                //file_put_contents('debug.txt', "Cell data:" . $cellData['value']."\n", FILE_APPEND);
                ob_end_clean();

                if (!empty($cellData['backgroundColor'])) {
                    $argbColor = rgbToArgb($cellData['backgroundColor']);
                    if ($argbColor) {
                        $cell->getStyle()->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB($argbColor);
                    }
                    //file_put_contents('debug.txt', "Cell ARGB:" . $argbColor."\n", FILE_APPEND);
                }
            }
        }

        // Save the updated spreadsheet with a new filename
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxFile);

        echo json_encode(['message' => "File saved successfully as $xlsxFile"]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to save file: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
