<?php
// Stage 3 – Department Classification
set_time_limit(0);
require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['np_dir'])) {
    header('Location: index.php'); exit;
}

$npDir    = $_POST['np_dir'];
$xlsxFile = $_POST['xlsx_file'];   // _BR_CHPF.xlsx
$shop     = $_POST['shop']      ?? '';
$date     = $_POST['date']      ?? '';
$deptFile = $_POST['dept_file'] ?? '';

// ── Load departments ──────────────────────────────────────────────────────────
if (!file_exists($deptFile)) {
    die('<p style="color:red;font-family:Arial;font-size:22px;padding:30px">שגיאה: קובץ מחלקות לא נמצא: ' . htmlspecialchars($deptFile) . '</p>');
}
$departments = json_decode(file_get_contents($deptFile), true) ?? [];
$deptLookup  = [];
foreach ($departments as $d) {
    if (isset($d['DepartmentName'], $d['DepartmentNumber'])) {
        $deptLookup[$d['DepartmentName']] = $d['DepartmentNumber'];
    }
}
$deptNames = array_keys($deptLookup);

// ── Load xlsx and read product names from col B ───────────────────────────────
try {
    $spreadsheet = IOFactory::load($npDir . '/' . $xlsxFile);
} catch (Exception $e) {
    die('<p style="color:red;font-family:Arial;font-size:22px;padding:30px">שגיאה בטעינת Excel: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

$sheet    = $spreadsheet->getActiveSheet();
$products = [];
for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
    $val = $sheet->getCell('B' . $row)->getValue();
    if ($val === null || trim((string)$val) === '') break;
    $products[] = ['row' => $row, 'name' => trim((string)$val)];
}

if (empty($products)) {
    die('<p style="color:red;font-family:Arial;font-size:22px;padding:30px">שגיאה: לא נמצאו שמות מוצרים בעמודה B.</p>');
}

// ── Read price comparison data from CHPF xlsx (col D=max, E=min, F=sale) ─────
$priceChecks = [];
foreach ($products as $p) {
    $row      = $p['row'];
    $barcode  = trim((string)$sheet->getCell('A' . $row)->getValue());
    $maxP     = trim((string)$sheet->getCell('D' . $row)->getValue());
    $minP     = trim((string)$sheet->getCell('E' . $row)->getValue());
    $saleP    = (float)$sheet->getCell('F' . $row)->getValue();
    if ($maxP === '' || $maxP === 'NA' || $minP === '' || $minP === 'NA') {
        $priceChecks[] = ['row' => $row, 'name' => $p['name'], 'barcode' => $barcode, 'sale' => $saleP, 'status' => 'na'];
    } else {
        $max  = (float)str_replace(',', '', $maxP);
        $min  = (float)str_replace(',', '', $minP);
        if ($saleP > $max)     $priceChecks[] = ['row' => $row, 'name' => $p['name'], 'barcode' => $barcode, 'sale' => $saleP, 'max' => $max, 'min' => $min, 'status' => 'above'];
        elseif ($saleP < $min) $priceChecks[] = ['row' => $row, 'name' => $p['name'], 'barcode' => $barcode, 'sale' => $saleP, 'max' => $max, 'min' => $min, 'status' => 'below'];
        else                   $priceChecks[] = ['row' => $row, 'name' => $p['name'], 'barcode' => $barcode, 'sale' => $saleP, 'max' => $max, 'min' => $min, 'status' => 'ok'];
    }
}
$pcNa       = count(array_filter($priceChecks, fn($c) => $c['status'] === 'na'));
$pcOk       = count(array_filter($priceChecks, fn($c) => $c['status'] === 'ok'));
$pcWarn     = array_filter($priceChecks, fn($c) => in_array($c['status'], ['above', 'below']));
$pcCompared = $pcOk + count($pcWarn);

// ── Load OpenAI key ───────────────────────────────────────────────────────────
$config = json_decode(file_get_contents(__DIR__ . '/../AIocr/config.json'), true);
$apiKey = $config['openai_api_key'] ?? null;
if (empty($apiKey)) die('Error: OpenAI API key not configured.');

// ── Build prompt ──────────────────────────────────────────────────────────────
$productList = implode("\n", array_map(fn($i, $p) => ($i + 1) . '. ' . $p['name'], array_keys($products), $products));
$deptList    = implode("\n", array_map(fn($i, $n) => ($i + 1) . '. ' . $n,          array_keys($deptNames), $deptNames));

$systemPrompt = <<<PROMPT
You are a supermarket product classification assistant.
You will receive a numbered list of products and a numbered list of supermarket departments.
Classify each product into the single most appropriate department.

Rules:
- The "department" field MUST exactly match one of the provided department names (copy verbatim).
- Maintain the same order as the input product list.
- For "note", write a very short reason (one sentence max).

Return ONLY valid JSON:
{"classifications": [{"index": 1, "product": "...", "department": "...", "note": "..."}, ...]}
PROMPT;

// ── Call OpenAI ───────────────────────────────────────────────────────────────
$payload = [
    'model'           => 'gpt-4.1-mini',
    'messages'        => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => "Departments:\n{$deptList}\n\nProducts to classify:\n{$productList}"],
    ],
    'max_tokens'      => 4000,
    'temperature'     => 0.0,
    'response_format' => ['type' => 'json_object'],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    $err = json_decode($response, true);
    die('<p style="color:red;font-family:Arial;font-size:22px;padding:30px">שגיאת OpenAI: ' . htmlspecialchars($err['error']['message'] ?? 'HTTP ' . $httpCode) . '</p>');
}

$result  = json_decode($response, true);
$content = $result['choices'][0]['message']['content'] ?? null;
$aiData  = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($aiData['classifications'])) {
    die('<p style="color:red;font-family:Arial;font-size:22px;padding:30px">שגיאה בניתוח תשובת OpenAI.</p>');
}

$classifications = $aiData['classifications'];
$usage           = $result['usage'] ?? [];

// ── Write to xlsx (col G = dept number, col H = dept name, col I = product name) ──
foreach ($classifications as $cl) {
    $idx      = (int)($cl['index'] ?? 0) - 1;
    if (!isset($products[$idx])) continue;
    $row      = $products[$idx]['row'];
    $deptName = $cl['department'] ?? '';
    $deptNum  = $deptLookup[$deptName] ?? '';
    $sheet->setCellValue('G' . $row, $deptNum);
    $sheet->setCellValue('H' . $row, $deptName);
}

foreach (['G', 'H'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// ── Write full department reference list: col I = number, col J = name ───────
$dRow = 2;
foreach ($deptLookup as $name => $num) {
    $sheet->setCellValue('I' . $dRow, $num);
    $sheet->setCellValue('J' . $dRow, $name);
    $dRow++;
}
foreach (['I', 'J'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Save as _DCL.xlsx (final)
$base     = pathinfo($xlsxFile, PATHINFO_FILENAME);
$dclFile  = $base . '_DCL.xlsx';
$dclPath  = $npDir . '/' . $dclFile;
try {
    (new Xlsx($spreadsheet))->save($dclPath);
    $saveOk = true;
} catch (Exception $e) {
    $saveOk  = false;
    $saveErr = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Harmonized – שלב 3: סיווג מחלקות</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 45px; }
        h2   { color: #2575a8; font-size: 36px; margin-bottom: 9px; }
        .sub { color: #777; font-size: 20px; margin-bottom: 36px; }
        .meta { display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 33px; }
        .meta-box { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 14px 24px; font-size: 20px; color: #555; }
        .meta-box strong { color: #333; }
        .saved-box { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px; padding: 21px 28px; max-width: 1100px; margin-bottom: 36px; font-size: 21px; color: #2a7d2a; font-weight: bold; }
        .saved-box.err { background: #fdecea; border-color: #f5c6c6; color: #c0392b; }
        table { border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.1); max-width: 1400px; width: 100%; margin-bottom: 36px; }
        th { background: #2575a8; color: #fff; padding: 14px 18px; font-size: 20px; text-align: right; }
        td { padding: 12px 18px; border-bottom: 1px solid #eee; font-size: 20px; color: #333; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .dept-num  { font-weight: bold; color: #2575a8; }
        .note-cell { color: #777; font-style: italic; font-size: 17px; }
        .btn-home  { display: inline-block; padding: 17px 50px; background: #2575a8; color: #fff; border: none; border-radius: 8px; font-size: 24px; cursor: pointer; text-decoration: none; }
        .btn-home:hover { background: #1a5c87; }
        .finish-banner { background: #e8f5e9; border: 2px solid #2a7d2a; border-radius: 10px; padding: 28px 36px; max-width: 1100px; margin-bottom: 36px; font-size: 27px; color: #2a7d2a; font-weight: bold; text-align: center; }
        .ok-box  { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px; padding: 21px 28px; max-width: 1100px; margin-bottom: 36px; font-size: 21px; color: #2a7d2a; font-weight: bold; }
        .warn-box { background: #fdecea; border: 2px solid #e57373; border-radius: 8px; padding: 21px 28px; max-width: 1100px; margin-bottom: 36px; }
        .warn-box h4 { color: #c0392b; font-size: 24px; margin: 0 0 14px; }
        .warn-row { font-size: 20px; color: #c0392b; margin: 6px 0; }
        .price-stats { font-size: 20px; color: #555; margin-bottom: 16px; }
        .price-stats span { margin-left: 28px; }
        .btn-copy { margin-top: 18px; padding: 12px 30px; background: #2575a8; color: #fff; border: none; border-radius: 7px; font-size: 20px; cursor: pointer; }
        .btn-copy:hover { background: #1a5c87; }
    </style>
</head>
<body>
<h2>NP Harmonized – שלב 3: סיווג מחלקות</h2>
<p class="sub">חנות: <?= htmlspecialchars($shop) ?> | תאריך: <?= htmlspecialchars($date) ?></p>

<div class="meta">
    <div class="meta-box"><strong>חנות:</strong> <?= htmlspecialchars($shop) ?></div>
    <div class="meta-box"><strong>מוצרים:</strong> <?= count($products) ?></div>
    <div class="meta-box"><strong>מחלקות:</strong> <?= count($deptNames) ?></div>
    <?php if (!empty($usage)): ?>
    <div class="meta-box"><strong>Tokens:</strong> <?= ($usage['total_tokens'] ?? '?') ?> (prompt: <?= ($usage['prompt_tokens'] ?? '?') ?>, completion: <?= ($usage['completion_tokens'] ?? '?') ?>)</div>
    <?php endif; ?>
</div>

<div class="saved-box <?= $saveOk ? '' : 'err' ?>">
    <?php if ($saveOk): ?>
        ✅ קובץ סופי נשמר: <?= htmlspecialchars($dclFile) ?>
    <?php else: ?>
        ❌ שגיאה בשמירה: <?= htmlspecialchars($saveErr) ?>
    <?php endif; ?>
</div>

<table>
    <tr><th>#</th><th>שם מוצר</th><th>מחלקה</th><th>מספר מחלקה</th><th>הערת AI</th></tr>
    <?php foreach ($classifications as $cl):
        $idx      = (int)($cl['index'] ?? 0) - 1;
        $deptName = $cl['department'] ?? '';
        $deptNum  = $deptLookup[$deptName] ?? '—';
        $product  = $cl['product'] ?? ($products[$idx]['name'] ?? '');
        $note     = $cl['note']    ?? '';
    ?>
    <tr>
        <td><?= (int)($cl['index'] ?? 0) ?></td>
        <td><?= htmlspecialchars($product) ?></td>
        <td><?= htmlspecialchars($deptName) ?></td>
        <td class="dept-num"><?= htmlspecialchars((string)$deptNum) ?></td>
        <td class="note-cell"><?= htmlspecialchars($note) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- ── Price range summary ───────────────────────────────────────────────────── -->
<h3 style="color:#555;font-size:27px;margin-bottom:18px">סיכום מחירים מול CHP</h3>
<div class="price-stats">
    <span>סה״כ שורות: <?= count($priceChecks) ?></span>
    <span>הושוו: <?= $pcCompared ?></span>
    <?php if ($pcNa > 0): ?>
    <span>ללא מחירי CHP (NA): <?= $pcNa ?></span>
    <?php endif; ?>
</div>

<?php if (empty($pcWarn) && $pcCompared > 0): ?>
<div class="ok-box">✅ כל המחירים בטווח</div>
<?php elseif (!empty($pcWarn)):
    $warnLines = [];
    foreach ($pcWarn as $w) {
        if ($w['status'] === 'above')
            $warnLines[] = "{$w['name']} ({$w['barcode']}) — מחיר מכירה " . number_format($w['sale'],2) . " גבוה ממקסימום " . number_format($w['max'],2) . " ב CHP";
        else
            $warnLines[] = "{$w['name']} ({$w['barcode']}) — מחיר מכירה " . number_format($w['sale'],2) . " נמוך ממינימום " . number_format($w['min'],2) . " ב CHP";
    }
    $copyText = "שים לב ל " . count($pcWarn) . " מוצרים עם מחירים מחוץ לטווח CHP\n" . implode("\n", $warnLines);
?>
<div class="warn-box">
    <h4>⚠ שים לב ל <?= count($pcWarn) ?> מוצרים עם מחירים מחוץ לטווח CHP</h4>
    <?php foreach ($warnLines as $line): ?>
    <div class="warn-row"><?= htmlspecialchars($line) ?></div>
    <?php endforeach; ?>
    <button type="button" class="btn-copy" onclick="copyWarn()">📋 העתק להודעת WhatsApp</button>
</div>
<script>
function copyWarn() {
    var text = <?= json_encode($copyText, JSON_UNESCAPED_UNICODE) ?>;
    navigator.clipboard.writeText(text).then(function() {
        var btn = document.querySelector('.btn-copy');
        var orig = btn.textContent;
        btn.textContent = '✔ הועתק!';
        setTimeout(function() { btn.textContent = orig; }, 2500);
    });
}
</script>
<?php endif; ?>

<div class="finish-banner">
    🎉 תהליך NP Harmonized הושלם בהצלחה!<br>
    <span style="font-size:20px;font-weight:normal">קבצים שנשמרו בתיקיית NP: _BR.xlsx → _CHPF.xlsx → _DCL.xlsx</span>
</div>

<a class="btn-home" href="index.php">חזור לתחילה</a>

</body>
</html>
