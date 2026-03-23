<?php
set_time_limit(0);
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

define('CONFIG_DIR', __DIR__ . '/../configDir');

// ── Validate input ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_POST['shop'])
    || !isset($_FILES['np_file'])
    || $_FILES['np_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php');
    exit;
}

$shop    = basename($_POST['shop']);
$deptFile = CONFIG_DIR . '/' . $shop . '_Departments.json';

if (!file_exists($deptFile)) {
    die('Error: Department config not found for shop: ' . htmlspecialchars($shop));
}

// ── Load departments ──────────────────────────────────────────────────────────
$departments = json_decode(file_get_contents($deptFile), true);
if (!$departments) die('Error: Could not parse department config.');

// Build lookup: DepartmentName → DepartmentNumber
$deptLookup = [];
foreach ($departments as $d) {
    if (isset($d['DepartmentName'], $d['DepartmentNumber'])) {
        $deptLookup[$d['DepartmentName']] = $d['DepartmentNumber'];
    }
}
$deptNames = array_keys($deptLookup);

// ── Save & load uploaded Excel ────────────────────────────────────────────────
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
$ext      = strtolower(pathinfo($_FILES['np_file']['name'], PATHINFO_EXTENSION));
$savedPath = $tmpDir . '/' . uniqid('npclassify_', true) . '.' . $ext;
move_uploaded_file($_FILES['np_file']['tmp_name'], $savedPath);

$spreadsheet = IOFactory::load($savedPath);
$sheet       = $spreadsheet->getActiveSheet();

// Read product names from column B starting row 2
$products = [];
for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
    $val = $sheet->getCell('B' . $row)->getValue();
    if ($val === null || trim((string)$val) === '') break;
    $products[] = ['row' => $row, 'name' => trim((string)$val)];
}

if (empty($products)) {
    die('Error: No product names found in column B from row 2.');
}

// ── Load OpenAI API key ───────────────────────────────────────────────────────
$configFile = __DIR__ . '/../AIocr/config.json';
if (!file_exists($configFile)) die('Error: AIocr/config.json not found.');
$config = json_decode(file_get_contents($configFile), true);
$apiKey = $config['openai_api_key'] ?? null;
if (empty($apiKey) || $apiKey === 'YOUR_NEW_API_KEY_HERE') die('Error: OpenAI API key not configured.');

// ── Build prompt ──────────────────────────────────────────────────────────────
$productList = implode("\n", array_map(
    fn($i, $p) => ($i + 1) . '. ' . $p['name'],
    array_keys($products), $products
));

$deptList = implode("\n", array_map(
    fn($i, $name) => ($i + 1) . '. ' . $name,
    array_keys($deptNames), $deptNames
));

$systemPrompt = <<<PROMPT
You are a supermarket product classification assistant.
You will receive a numbered list of products and a numbered list of supermarket departments.
Classify each product into the single most appropriate department.

Rules:
- The "department" field in your response MUST exactly match one of the provided department names (copy it verbatim).
- Maintain the same order as the input product list.
- For the "note" field, write a very short reason for your choice (one sentence max).

Return ONLY a valid JSON object in this exact format:
{
  "classifications": [
    {"index": 1, "product": "...", "department": "...", "note": "..."},
    ...
  ]
}
PROMPT;

$userMessage = "Departments:\n{$deptList}\n\nProducts to classify:\n{$productList}";

// ── Call OpenAI ───────────────────────────────────────────────────────────────
$payload = [
    'model'           => 'gpt-4.1-mini',
    'messages'        => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userMessage],
    ],
    'max_tokens'      => 4000,
    'temperature'     => 0.0,
    'response_format' => ['type' => 'json_object'],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    $errData = json_decode($response, true);
    $errMsg  = $errData['error']['message'] ?? ('HTTP ' . $httpCode);
    die('OpenAI API error: ' . htmlspecialchars($errMsg));
}

$result  = json_decode($response, true);
$content = $result['choices'][0]['message']['content'] ?? null;
if (!$content) die('Error: Empty response from OpenAI.');

$aiData = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($aiData['classifications'])) {
    die('Error: Could not parse OpenAI classification response.');
}

$classifications = $aiData['classifications'];

// ── Write results to Excel ────────────────────────────────────────────────────
// Col G = DepartmentNumber, Col H = DepartmentName, Col I = product name
foreach ($classifications as $cl) {
    $idx     = (int)($cl['index'] ?? 0) - 1; // 0-based
    if (!isset($products[$idx])) continue;

    $row     = $products[$idx]['row'];
    $deptName   = $cl['department'] ?? '';
    $deptNumber = $deptLookup[$deptName] ?? '';
    $productName = $products[$idx]['name'];

    $sheet->setCellValue('G' . $row, $deptNumber);
    $sheet->setCellValue('H' . $row, $deptName);
    $sheet->setCellValue('I' . $row, $productName);
}

$writer = new Xlsx($spreadsheet);
$writer->save($savedPath);

// ── Usage info ────────────────────────────────────────────────────────────────
$usage = $result['usage'] ?? [];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Classifier – תוצאות</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 30px; }
        h2 { color: #2575a8; }
        .meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 22px; }
        .meta-box { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 10px 18px; font-size: 13px; color: #555; }
        .meta-box strong { color: #333; }
        table { border-collapse: collapse; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.1); width: 100%; max-width: 1000px; }
        th { background: #2575a8; color: #fff; padding: 10px 14px; font-size: 13px; text-align: right; }
        td { padding: 9px 14px; border-bottom: 1px solid #eee; font-size: 13px; color: #333; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f0f6fb; }
        .dept-num { font-weight: bold; color: #2575a8; }
        .note-cell { color: #777; font-style: italic; font-size: 12px; }
        .success-msg { padding: 12px 18px; background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 6px; color: #2a7d2a; font-weight: bold; max-width: 800px; margin-bottom: 20px; }
        .btn { display: inline-block; margin-top: 18px; padding: 10px 22px; background: #2575a8; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .btn:hover { background: #1a5c87; }
        .btn-dl { background: #2a7d2a; margin-right: 10px; }
        .btn-dl:hover { background: #1e5c1e; }
    </style>
</head>
<body>
<h2>NP Department Classifier – תוצאות</h2>

<div class="success-msg">✔ <?= count($classifications) ?> מוצרים סווגו ונכתבו לקובץ Excel (עמודות G, H, I)</div>

<div class="meta">
    <div class="meta-box"><strong>חנות:</strong> <?= htmlspecialchars($shop) ?></div>
    <div class="meta-box"><strong>מוצרים:</strong> <?= count($products) ?></div>
    <div class="meta-box"><strong>מחלקות:</strong> <?= count($deptNames) ?></div>
    <?php if (!empty($usage)): ?>
    <div class="meta-box"><strong>Tokens:</strong> <?= ($usage['total_tokens'] ?? '?') ?> (prompt: <?= ($usage['prompt_tokens'] ?? '?') ?>, completion: <?= ($usage['completion_tokens'] ?? '?') ?>)</div>
    <?php endif; ?>
    <div class="meta-box"><strong>מודל:</strong> <?= htmlspecialchars($result['model'] ?? 'gpt-4.1-mini') ?></div>
</div>

<table>
    <tr>
        <th>#</th>
        <th>שם מוצר</th>
        <th>מחלקה</th>
        <th>מספר מחלקה</th>
        <th>הערת AI</th>
    </tr>
    <?php foreach ($classifications as $cl):
        $idx      = (int)($cl['index'] ?? 0) - 1;
        $deptName = $cl['department'] ?? '';
        $deptNum  = $deptLookup[$deptName] ?? '—';
        $note     = $cl['note'] ?? '';
        $product  = $cl['product'] ?? ($products[$idx]['name'] ?? '');
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

<a class="btn btn-dl" href="download.php?file=<?= urlencode(basename($savedPath)) ?>">הורד Excel מעודכן</a>
<a class="btn" href="index.php">חזור</a>
</body>
</html>
