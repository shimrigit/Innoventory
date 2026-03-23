<?php
set_time_limit(0);
session_start();

define('NP_BASE_DIR', 'C:/Users/Shimri-SAS/Dropbox (Personal)/PC/Documents/LS Consulting/Business/Retailomatics/InvoiceArchive/BernardYahudArchive/NewProducts');

// ── Validate POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['np_dir']) || empty($_POST['excel_file'])) {
    header('Location: index.php');
    exit;
}

$npDirName  = basename($_POST['np_dir']);
$excelName  = basename($_POST['excel_file']);
$npDirPath  = NP_BASE_DIR . '/' . $npDirName;
$excelPath  = $npDirPath . '/' . $excelName;

if (!is_dir($npDirPath) || !file_exists($excelPath)) {
    header('Location: index.php');
    exit;
}

// ── Load OpenAI API key ───────────────────────────────────────────────────────
$configFile = __DIR__ . '/../AIocr/config.json';
if (!file_exists($configFile)) die('Error: AIocr/config.json not found.');
$config = json_decode(file_get_contents($configFile), true);
$apiKey = $config['openai_api_key'] ?? null;
if (empty($apiKey) || $apiKey === 'YOUR_NEW_API_KEY_HERE') die('Error: OpenAI API key not configured.');

// ── Parse JPEG filenames ──────────────────────────────────────────────────────
// Expected: "yyyy-mm-dd at hh.mm.ss P.jpeg"
function parseNPFilename($filename) {
    if (preg_match('/^(\d{4}-\d{2}-\d{2}) at (\d{2})\.(\d{2})\.(\d{2}) ([\d.]+)\.jpe?g$/i', $filename, $m)) {
        return [
            'filename' => $filename,
            'sortKey'  => $m[1] . $m[2] . $m[3] . $m[4], // yyyymmddhhmmss
            'price'    => $m[5],
        ];
    }
    return null;
}

$rawFiles = glob($npDirPath . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
$jpegItems = [];
foreach ($rawFiles as $fullPath) {
    $parsed = parseNPFilename(basename($fullPath));
    if ($parsed) {
        $parsed['fullPath'] = $fullPath;
        $jpegItems[] = $parsed;
    }
}
usort($jpegItems, fn($a, $b) => strcmp($a['sortKey'], $b['sortKey']));

// ── OpenAI barcode call ───────────────────────────────────────────────────────
function askForBarcode($imagePath, $apiKey) {
    $imageData = base64_encode(file_get_contents($imagePath));
    $ext       = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
    $mime      = in_array($ext, ['jpg','jpeg']) ? 'image/jpeg' : 'image/png';

    $payload = [
        'model'    => 'gpt-4.1-mini',
        'messages' => [
            [
                'role'    => 'system',
                'content' => 'You are a barcode reader. Look at the product image and find the barcode number printed on the product (EAN-13, EAN-8, UPC-A, or any numeric barcode). Return ONLY valid JSON with one field: {"barcode": "<digits>"}. If no barcode is visible return {"barcode": "NOT_FOUND"}.',
            ],
            [
                'role'    => 'user',
                'content' => [
                    ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$imageData}"]]
                ],
            ],
        ],
        'max_tokens'      => 100,
        'temperature'     => 0.0,
        'response_format' => ['type' => 'json_object'],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return 'ERROR';
    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) return 'ERROR';
    $content = json_decode($result['choices'][0]['message']['content'], true);
    return $content['barcode'] ?? 'NOT_FOUND';
}

// ── Streaming setup ───────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) ob_end_flush();
ob_implicit_flush(true);

function sf() { echo str_pad('', 512, ' '); flush(); }
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Barcode – עיבוד</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 24px; }
        h2 { color: #2575a8; margin-bottom: 16px; }
        .progress-box { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 14px 18px; max-width: 780px; margin-bottom: 24px; }
        .pl  { font-size: 13px; color: #555; margin: 3px 0; }
        .pl.wait { color: #999; font-style: italic; }
        .pl.done { color: #2a7d2a; }
        .pl.err  { color: #c0392b; }

        /* ── Verification table ── */
        .verify-wrap { max-width: 1100px; }
        table.vtbl { border-collapse: collapse; width: 100%; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 6px rgba(0,0,0,.1); }
        table.vtbl th { background: #2575a8; color: #fff; padding: 10px 12px; font-size: 13px; }
        table.vtbl td { padding: 8px 12px; border-bottom: 1px solid #eee; vertical-align: middle; font-size: 13px; }
        table.vtbl tr:last-child td { border-bottom: none; }
        input.bc { width: 220px; padding: 8px 10px; border: 1px solid #bbb; border-radius: 4px; font-size: 26px; font-weight: bold; }
        .fname { font-size: 26px; color: #333; }
        .inline-img { width: 420px; display: block; border-radius: 4px; border: 1px solid #ddd; transition: width .2s; }
        .zoom-btns { margin-top: 6px; display: flex; gap: 6px; }
        .zoom-btns button { padding: 4px 14px; font-size: 18px; font-weight: bold; border: 1px solid #bbb; border-radius: 4px; background: #f0f0f0; cursor: pointer; line-height: 1; }
        .zoom-btns button:hover { background: #ddd; }
        .btn-done { margin-top: 20px; padding: 12px 36px; background: #2a7d2a; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
        .btn-done:hover { background: #1e5c1e; }
    </style>
</head>
<body>
<h2>NP Barcode Reader – עיבוד</h2>

<?php if (empty($jpegItems)): ?>
<p style="color:#c0392b;">לא נמצאו קבצי JPEG תואמים בתיקייה.</p>
<a href="index.php">חזור</a>
<?php
    echo '</body></html>';
    exit;
endif;
?>

<div class="progress-box" id="progressBox">
<?php
sf();

$results = [];

foreach ($jpegItems as $idx => $item) {
    $z   = $idx + 1;
    $fn  = htmlspecialchars($item['filename']);

    echo "<p class='pl wait' id='p{$z}'>איטרציה {$z} עבור קובץ {$fn}...</p>";
    sf();

    $barcode = askForBarcode($item['fullPath'], $apiKey);

    echo "<script>document.getElementById('p{$z}').className='pl done';document.getElementById('p{$z}').textContent='איטרציה {$z} עבור קובץ {$fn} ברקוד {$barcode}';</script>";
    sf();

    $results[] = [
        'filename' => $item['filename'],
        'fullPath' => $item['fullPath'],
        'price'    => $item['price'],
        'barcode'  => $barcode,
    ];
}

// Store in session for save.php
$_SESSION['npbarcode_excel']   = $excelPath;
$_SESSION['npbarcode_results'] = $results;
?>
</div>

<!-- ── Verification form ─────────────────────────────────────────────────── -->
<div class="verify-wrap">
<h3>אמת ברקודים</h3>
<form action="save.php" method="post">
<table class="vtbl">
    <tr>
        <th>#</th>
        <th>ברקוד (ניתן לעריכה)</th>
        <th>שם קובץ</th>
        <th>תמונה</th>
    </tr>
    <?php foreach ($results as $i => $r):
        $n = $i + 1;
        $imgUrl = 'serve_image.php?path=' . urlencode($r['fullPath']);
    ?>
    <tr>
        <td><?= $n ?></td>
        <td><input class="bc" type="text" name="barcode[<?= $i ?>]" value="<?= htmlspecialchars($r['barcode']) ?>"></td>
        <td class="fname"><?= htmlspecialchars($r['filename']) ?></td>
        <td>
            <img class="inline-img" id="img<?= $i ?>" src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($r['filename']) ?>">
            <div class="zoom-btns">
                <button type="button" onclick="zoom('img<?= $i ?>', 1.3)">＋</button>
                <button type="button" onclick="zoom('img<?= $i ?>', 0.77)">－</button>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<button type="submit" class="btn-done">Done – שמור לאקסל</button>
</form>
</div>

<script>
function zoom(id, factor) {
    var img = document.getElementById(id);
    img.style.width = Math.round(img.offsetWidth * factor) + 'px';
}
</script>
</body>
</html>
