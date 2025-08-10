<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = isset($_GET['file']) ? $_GET['file'] : 'data.xlsx';

$spreadsheet = IOFactory::load($inputFileName);
$sheet = $spreadsheet->getActiveSheet();
$rows = [];

foreach ($sheet->getRowIterator() as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false); // This loops through all cells, including empty ones
    $rowData = [];
    foreach ($cellIterator as $cell) {
        $cellValue = $cell->getValue() === null ? '' : $cell->getValue();
        $cellStyle = $sheet->getStyle($cell->getCoordinate());
        $cellColor = $cellStyle->getFill()->getStartColor()->getARGB();
        $fontColor = $cellStyle->getFont()->getColor()->getARGB();

        // Get border styles
        $borders = $cellStyle->getBorders();
        $borderData = [
            'top' => $borders->getTop()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
            'right' => $borders->getRight()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
            'bottom' => $borders->getBottom()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
            'left' => $borders->getLeft()->getBorderStyle() !== \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE
        ];

        $rowData[] = [
            'value' => $cellValue,
            'cellColor' => $cellColor,
            'fontColor' => $fontColor,
            'borders' => $borderData
        ];
    }
    $rows[] = $rowData;
}

// Ensure B1, B2, B3, B4, B5 are always included
for ($i = 0; $i < 5; $i++) {
    if (!isset($rows[$i])) {
        $rows[$i] = [
            ['value' => '', 'cellColor' => 'FFFFFFFF', 'fontColor' => 'FF000000', 'borders' => ['top' => false, 'right' => false, 'bottom' => false, 'left' => false]],
            ['value' => '', 'cellColor' => 'FFFFFFFF', 'fontColor' => 'FF000000', 'borders' => ['top' => false, 'right' => false, 'bottom' => false, 'left' => false]]
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($rows);
?>
