<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fileName = $input['fileName'] ?? '';

    $filePath = __DIR__ . '/uploads/' . basename($fileName);
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found.']);
        exit;
    }

    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = [];

    foreach ($sheet->toArray(null, true, true, true) as $row) {
        $rows[] = array_values($row);
    }

    header('Content-Type: application/json');
    echo json_encode(['xlsxJson' => $rows]);
}
?>
