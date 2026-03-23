<?php
define('NP_BASE_DIR', 'C:/Users/Shimri-SAS/Dropbox (Personal)/PC/Documents/LS Consulting/Business/Retailomatics/InvoiceArchive/BernardYahudArchive/NewProducts');

// ── AJAX: return xlsx files for a given directory ─────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'get_excel') {
    header('Content-Type: application/json');
    $dir = realpath($_GET['dir'] ?? '');
    $baseReal = realpath(NP_BASE_DIR);
    // Security: must be inside NP_BASE_DIR
    if (!$dir || !$baseReal || strpos($dir, $baseReal) !== 0) {
        echo json_encode([]);
        exit;
    }
    $files = glob($dir . '/*.xlsx');
    $names = array_map('basename', $files ?: []);
    echo json_encode($names);
    exit;
}

// ── Scan for NP directories ───────────────────────────────────────────────────
$npDirs = [];
if (is_dir(NP_BASE_DIR)) {
    $entries = scandir(NP_BASE_DIR);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = NP_BASE_DIR . '/' . $entry;
        if (is_dir($full) && preg_match('/^\d{2}-\d{2}-\d{2} NP$/i', $entry)) {
            $npDirs[] = $entry;
        }
    }
    rsort($npDirs); // newest first
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Barcode Reader</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.15); padding: 40px 50px; width: 460px; }
        h2 { margin: 0 0 28px; color: #333; text-align: center; font-size: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; font-size: 14px; }
        select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #fafafa; margin-bottom: 18px; }
        select:disabled { background: #eee; color: #aaa; }
        button { display: block; width: 100%; margin-top: 10px; padding: 12px; background: #2575a8; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
        button:disabled { background: #aaa; cursor: not-allowed; }
        button:hover:not(:disabled) { background: #1a5c87; }
        .spinner { display: none; text-align: center; margin-top: 12px; color: #888; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h2>NP Barcode Reader</h2>
    <form action="process.php" method="post" id="mainForm">

        <label for="np_dir">בחר תיקיית NP</label>
        <select name="np_dir" id="np_dir" required>
            <option value="">-- בחר תיקייה --</option>
            <?php foreach ($npDirs as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="excel_file">בחר קובץ Excel</label>
        <select name="excel_file" id="excel_file" required disabled>
            <option value="">-- בחר תיקייה קודם --</option>
        </select>

        <button type="submit" id="submitBtn" disabled>התחל עיבוד</button>
    </form>
    <div class="spinner" id="spinner">טוען...</div>
</div>

<script>
const baseDir = <?= json_encode(NP_BASE_DIR) ?>;

document.getElementById('np_dir').addEventListener('change', function () {
    const dir = this.value;
    const excelSel = document.getElementById('excel_file');
    const submitBtn = document.getElementById('submitBtn');

    excelSel.disabled = true;
    excelSel.innerHTML = '<option value="">טוען...</option>';
    submitBtn.disabled = true;

    if (!dir) {
        excelSel.innerHTML = '<option value="">-- בחר תיקייה קודם --</option>';
        return;
    }

    fetch('index.php?action=get_excel&dir=' + encodeURIComponent(baseDir + '/' + dir))
        .then(r => r.json())
        .then(files => {
            if (files.length === 0) {
                excelSel.innerHTML = '<option value="">לא נמצאו קבצי Excel</option>';
            } else {
                excelSel.innerHTML = '<option value="">-- בחר קובץ --</option>';
                files.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f;
                    opt.textContent = f;
                    excelSel.appendChild(opt);
                });
                excelSel.disabled = false;
            }
        });
});

document.getElementById('excel_file').addEventListener('change', function () {
    document.getElementById('submitBtn').disabled = !this.value;
});

document.getElementById('mainForm').addEventListener('submit', function () {
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').textContent = 'מעבד...';
    document.getElementById('spinner').style.display = 'block';
});
</script>
</body>
</html>
