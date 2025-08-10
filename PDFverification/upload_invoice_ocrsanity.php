<?php
require __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle XLSX
    if (isset($_FILES['xlsx']) && $_FILES['xlsx']['error'] === UPLOAD_ERR_OK) {
        $xlsxTmpPath = $_FILES['xlsx']['tmp_name'];
        $xlsxName = basename($_FILES['xlsx']['name']);
        $xlsxPath = $uploadDir . $xlsxName;
        move_uploaded_file($xlsxTmpPath, $xlsxPath);

        $spreadsheet = IOFactory::load($xlsxPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];
        foreach ($sheet->toArray(null, true, true, true) as $row) {
            $rows[] = array_values($row);
        }
        $response['xlsxJson'] = $rows;
    }

    // Handle PDF
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $pdfTmpPath = $_FILES['pdf']['tmp_name'];
        $pdfName = basename($_FILES['pdf']['name']);
        $pdfPath = $uploadDir . $pdfName;
        move_uploaded_file($pdfTmpPath, $pdfPath);

        // Return relative path for PDF.js
        $response['pdfPath'] = 'uploads/' . $pdfName;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}
?>
