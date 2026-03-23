<?php
set_time_limit(0); // long-running scraper — no limit for this script only
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ── Streaming setup ───────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

function streamFlush() {
    echo str_pad('', 512, ' '); // pad to force browser render
    flush();
}

// ── Validate upload ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !isset($_FILES['excel_file'])
    || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php');
    exit;
}

$uploadedTmp  = $_FILES['excel_file']['tmp_name'];
$originalName = basename($_FILES['excel_file']['name']);
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($ext, ['xlsx', 'xls'])) {
    header('Location: index.php');
    exit;
}

// Save to a persistent temp location so we can write back to it
$tempDir  = __DIR__ . '/tmp';
if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
$savedPath = $tempDir . '/' . uniqid('chp_', true) . '.' . $ext;
move_uploaded_file($uploadedTmp, $savedPath);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Call chp_scraper.js for one barcode and return decoded result array or null.
 */
function callCHP($barcode, $city = 'תל אביב') {
    $scriptPath = realpath(__DIR__ . '/../commercialLayer/chp_scraper.js');
    if (!$scriptPath) return null;

    $cmd = 'node ' . escapeshellarg($scriptPath)
         . ' ' . escapeshellarg((string)$barcode)
         . ' ' . escapeshellarg($city)
         . ' 2>&1';

    $raw = shell_exec($cmd);
    if (empty($raw)) return null;

    // Separate debug lines from JSON output
    $jsonLines = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (preg_match('/^(Navigating|Page loaded|Entering|Clicking|Waiting|Extracting|Starting|Search completed|Using Node|Launching|Searching)/i', $line)) {
            continue; // debug/status — skip
        }
        $jsonLines[] = $line;
    }

    $json = implode('', $jsonLines);
    $data = json_decode($json, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $data : null;
}

/**
 * Parse a CHP result into the fields we need.
 * Returns array: hebrewName, returnedBarcode, maxPrice, minPrice
 */
function parseCHPResult($data) {
    $out = [
        'hebrewName'      => 'NA',
        'returnedBarcode' => 'NA',
        'maxPrice'        => 'NA',
        'minPrice'        => 'NA',
    ];

    if (!$data) return $out;

    $productInfo  = $data['productInfo'] ?? $data['productName'] ?? '';
    $productName  = '';
    $manufacturer = '';
    $barcode      = '';

    // Format 1: "שם מוצר (יצרן/מותג: יצרן, ברקוד: 1234567890123)"
    if (preg_match('/^(.+?)\s*\(יצרן\/מותג:\s*(.+?),\s*ברקוד:\s*(\d+)\)/u', $productInfo, $m)) {
        $productName  = trim($m[1]);
        $manufacturer = trim($m[2]);
        $barcode      = trim($m[3]);
    }
    // Format 2: "שם מוצר (ברקוד: 1234567890123)"
    elseif (preg_match('/^(.+?)\s*\(ברקוד:\s*(\d+)\)/u', $productInfo, $m)) {
        $productName = trim($m[1]);
        $barcode     = trim($m[2]);
    }
    // Fallback
    else {
        $productName = trim($productInfo);
    }

    // Build Hebrew name string
    $out['hebrewName']      = !empty($manufacturer) ? "{$productName}, {$manufacturer}" : $productName;
    if (empty($out['hebrewName'])) $out['hebrewName'] = 'NA';
    $out['returnedBarcode'] = $barcode ?: 'NA';

    // Extract numeric prices
    $numericPrices = [];
    foreach ($data['prices'] ?? [] as $entry) {
        $p = str_replace(',', '', trim($entry['price'] ?? ''));
        if (is_numeric($p)) $numericPrices[] = (float)$p;
    }

    if (!empty($numericPrices)) {
        $out['maxPrice'] = number_format(max($numericPrices), 2);
        $out['minPrice'] = number_format(min($numericPrices), 2);
    }

    return $out;
}

// ── HTML page open ────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>CHP Fetch – עיבוד</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 30px; }
        h2 { color: #2575a8; }
        .progress-box { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px; max-width: 700px; }
        .progress-line { font-size: 13px; color: #555; margin: 4px 0; }
        .progress-line.done  { color: #2a7d2a; }
        .progress-line.error { color: #c0392b; }
        table { border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.1); max-width: 100%; }
        th { background: #2575a8; color: #fff; padding: 10px 14px; text-align: right; font-size: 13px; }
        td { padding: 8px 14px; border-bottom: 1px solid #eee; font-size: 13px; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f0f6fb; }
        .success-msg { margin-top: 18px; padding: 12px 18px; background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 6px; color: #2a7d2a; font-weight: bold; max-width: 700px; }
        .btn { display: inline-block; margin-top: 14px; padding: 10px 22px; background: #2575a8; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn:hover { background: #1a5c87; }
        .btn-back { background: #888; margin-right: 10px; }
    </style>
</head>
<body>
<h2>CHP Fetch – עיבוד ברקודים</h2>
<div class="progress-box">
<?php
streamFlush();

// ── Load Excel & read barcodes ────────────────────────────────────────────────
try {
    $spreadsheet = IOFactory::load($savedPath);
} catch (Exception $e) {
    echo '<p class="progress-line error">שגיאה בטעינת הקובץ: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></body></html>';
    exit;
}

$sheet       = $spreadsheet->getActiveSheet();
$highestRow  = $sheet->getHighestRow();

// Collect barcodes from A2 downward (stop at first empty cell)
$barcodes = [];
for ($row = 2; $row <= $highestRow; $row++) {
    $val = $sheet->getCell('A' . $row)->getValue();
    if ($val === null || $val === '') break;
    $barcodes[] = ['row' => $row, 'barcode' => trim((string)$val)];
}

if (empty($barcodes)) {
    echo '<p class="progress-line error">לא נמצאו ברקודים בעמודה A החל מ-A2.</p>';
    echo '</div></body></html>';
    exit;
}

// ── Iterate barcodes ──────────────────────────────────────────────────────────
$results = [];
$city    = 'תל אביב';

foreach ($barcodes as $idx => $item) {
    $z       = $idx + 1;
    $barcode = $item['barcode'];

    echo '<p class="progress-line">איטרציה ' . $z . ' בעיבוד עבור ברקוד ' . htmlspecialchars($barcode) . '...</p>';
    streamFlush();

    $chpData = callCHP($barcode, $city);
    $parsed  = parseCHPResult($chpData);

    $results[] = array_merge(['row' => $item['row'], 'inputBarcode' => $barcode], $parsed);

    echo '<p class="progress-line done">✔ ' . htmlspecialchars($parsed['hebrewName']) . ' | ברקוד: ' . htmlspecialchars($parsed['returnedBarcode']) . ' | מקס: ' . $parsed['maxPrice'] . ' | מין: ' . $parsed['minPrice'] . '</p>';
    streamFlush();
}

echo '</div>'; // close progress-box
streamFlush();

// ── Write results back to Excel ───────────────────────────────────────────────
foreach ($results as $r) {
    $row = $r['row'];
    $sheet->setCellValue('B' . $row, $r['hebrewName']);
    $sheet->setCellValue('C' . $row, $r['returnedBarcode']);
    $sheet->setCellValue('D' . $row, $r['maxPrice']);
    $sheet->setCellValue('E' . $row, $r['minPrice']);
}

$writer = new Xlsx($spreadsheet);
$writer->save($savedPath);

// ── Results table ─────────────────────────────────────────────────────────────
echo '<h3>תוצאות</h3>';
echo '<table>';
echo '<tr><th>#</th><th>ברקוד קלט</th><th>שם מוצר</th><th>ברקוד CHP</th><th>מחיר מקסימלי</th><th>מחיר מינימלי</th></tr>';
foreach ($results as $idx => $r) {
    echo '<tr>'
        . '<td>' . ($idx + 1) . '</td>'
        . '<td>' . htmlspecialchars($r['inputBarcode']) . '</td>'
        . '<td>' . htmlspecialchars($r['hebrewName']) . '</td>'
        . '<td>' . htmlspecialchars($r['returnedBarcode']) . '</td>'
        . '<td>' . htmlspecialchars($r['maxPrice']) . '</td>'
        . '<td>' . htmlspecialchars($r['minPrice']) . '</td>'
        . '</tr>';
}
echo '</table>';

// ── Download link ─────────────────────────────────────────────────────────────
$downloadFile = basename($savedPath);
echo '<div class="success-msg">✔ הקובץ עודכן בהצלחה עם התוצאות בעמודות B–E</div>';
echo '<a class="btn" href="download.php?file=' . urlencode($downloadFile) . '">הורד קובץ Excel מעודכן</a>';
echo '<a class="btn btn-back" href="index.php">חזור</a>';
?>
</body>
</html>
