<?php
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (isset($_GET['file'])) {
    $xlsxFile = $_GET['file'];

    try {
        // Load the spreadsheet
        $spreadsheet = IOFactory::load($xlsxFile);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        $data = [];
        for ($row = 1; $row <= $highestRow; ++$row) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                $cellCoordinate = Coordinate::stringFromColumnIndex($col) . $row;
                $cell = $sheet->getCell($cellCoordinate);
                $cellValue = $cell->getValue();
                $cellColor = $sheet->getStyle($cellCoordinate)->getFill()->getStartColor()->getARGB();
                $fontColor = $sheet->getStyle($cellCoordinate)->getFont()->getColor()->getARGB();
                $borders = [
                    'top' => $sheet->getStyle($cellCoordinate)->getBorders()->getTop()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
                    'right' => $sheet->getStyle($cellCoordinate)->getBorders()->getRight()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
                    'bottom' => $sheet->getStyle($cellCoordinate)->getBorders()->getBottom()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
                    'left' => $sheet->getStyle($cellCoordinate)->getBorders()->getLeft()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
                ];
                $rowData[] = [
                    'value' => $cellValue,
                    'cellColor' => $cellColor,
                    'fontColor' => $fontColor,
                    'borders' => $borders,
                ];
            }
            $data[] = $rowData;
        }

        echo json_encode($data);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load file: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No file specified']);
}
