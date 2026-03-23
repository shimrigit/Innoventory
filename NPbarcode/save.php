<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ── Validate ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_SESSION['npbarcode_excel'])
    || !isset($_SESSION['npbarcode_results'])
    || !isset($_POST['barcode'])) {
    header('Location: index.php');
    exit;
}

$excelPath = $_SESSION['npbarcode_excel'];
$stored    = $_SESSION['npbarcode_results']; // [{filename, price, barcode}, ...]
$barcodes  = $_POST['barcode'];              // [0 => "...", 1 => "...", ...]

if (!file_exists($excelPath)) {
    die('Error: Excel file not found at ' . htmlspecialchars($excelPath));
}

// ── Load & write Excel ────────────────────────────────────────────────────────
$spreadsheet = IOFactory::load($excelPath);
$sheet       = $spreadsheet->getActiveSheet();

$rows = [];
foreach ($stored as $i => $item) {
    $barcode = trim($barcodes[$i] ?? '');
    $price   = $item['price'];

    $row = $i + 2; // A2, B2, ...
    $sheet->setCellValue('A' . $row, $barcode);
    $sheet->setCellValue('B' . $row, $price);

    $rows[] = ['barcode' => $barcode, 'price' => $price, 'filename' => $item['filename']];
}

$writer = new Xlsx($spreadsheet);
$writer->save($excelPath);

// Clear session data
unset($_SESSION['npbarcode_excel'], $_SESSION['npbarcode_results']);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Barcode – שמירה</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 30px; }
        h2 { color: #2575a8; }
        .success-msg { padding: 12px 18px; background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 6px; color: #2a7d2a; font-weight: bold; max-width: 720px; margin-bottom: 20px; }
        table { border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.1); }
        th { background: #2575a8; color: #fff; padding: 10px 16px; font-size: 13px; }
        td { padding: 8px 16px; border-bottom: 1px solid #eee; font-size: 13px; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f0f6fb; }
        .btn { display: inline-block; margin-top: 18px; padding: 10px 22px; background: #2575a8; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn:hover { background: #1a5c87; }
    </style>
</head>
<body>
<h2>NP Barcode Reader – שמירה הושלמה</h2>
<div class="success-msg">✔ <?= count($rows) ?> שורות נכתבו לקובץ Excel (עמודה A – ברקוד, עמודה B – מחיר)</div>

<table>
    <tr>
        <th>#</th>
        <th>ברקוד</th>
        <th>מחיר</th>
        <th>קובץ</th>
    </tr>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($r['barcode']) ?></td>
        <td><?= htmlspecialchars($r['price']) ?></td>
        <td><?= htmlspecialchars($r['filename']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<a class="btn" href="index.php">חזור לתחילה</a>
</body>
</html>
