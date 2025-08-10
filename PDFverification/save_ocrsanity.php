<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$data = json_decode(file_get_contents('php://input'), true);
$gridData = $data['data'] ?? [];
$fileName = basename($data['fileName'] ?? 'OCRsanity_' . date('dmY_His') . '.xlsx');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

foreach ($gridData as $rowIndex => $row) {
    foreach ($row as $colIndex => $cellValue) {
        $cell = $sheet->getCellByColumnAndRow($colIndex + 1, $rowIndex + 1);
        $cell->setValueExplicit($cellValue, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    }
}

$saveDir = __DIR__ . '/uploads/';
if (!file_exists($saveDir)) {
    mkdir($saveDir, 0777, true);
}

$savePath = $saveDir . $fileName;
$writer = new Xlsx($spreadsheet);
$writer->save($savePath);

echo json_encode(['status' => 'success', 'savedAs' => $fileName]);
?>
