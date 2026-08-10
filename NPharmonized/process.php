<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/flow_mode.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['np_dir']) || empty($_POST['xlsx_file'])) {
    header('Location: index.php');
    exit;
}

$npDir    = $_POST['np_dir'];
$xlsxFile = $_POST['xlsx_file'];
$shop     = $_POST['shop']        ?? '';
$date     = $_POST['date']        ?? '';
$deptFile = $_POST['dept_file']   ?? '';
$mode     = npFlowMode();

// ── Read prices from column F ─────────────────────────────────────────────────
$prices = [];
try {
    $spreadsheet = IOFactory::load($npDir . '/' . $xlsxFile);
    $sheet       = $spreadsheet->getActiveSheet();
    for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
        $val = $sheet->getCell('F' . $row)->getValue();
        if ($val === null || $val === '') break;
        $prices[] = trim((string)$val);
    }
} catch (Exception $e) {
    die('<p style="color:red;font-family:Arial;padding:20px">שגיאה בטעינת קובץ Excel: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ── Collect & sort WhatsApp JPEG files ────────────────────────────────────────
$pattern = '/^WhatsApp Image (\d{4}-\d{2}-\d{2}) at (\d{2})\.(\d{2})\.(\d{2})\.jpeg$/i';

$allFiles  = glob($npDir . '/*.jpeg') ?: [];
$jpegItems = [];

foreach ($allFiles as $fullPath) {
    $fname = basename($fullPath);
    if (preg_match($pattern, $fname, $m)) {
        $sortKey     = $m[1] . $m[2] . $m[3] . $m[4]; // yyyymmddhhmmss
        $dateTimePart = $m[1] . ' at ' . $m[2] . '.' . $m[3] . '.' . $m[4]; // yyyy-mm-dd at hh.mm.ss
        $jpegItems[] = [
            'fullPath'     => $fullPath,
            'fname'        => $fname,
            'sortKey'      => $sortKey,
            'dateTimePart' => $dateTimePart,
        ];
    }
}

usort($jpegItems, fn($a, $b) => strcmp($a['sortKey'], $b['sortKey']));

// ── Rename ────────────────────────────────────────────────────────────────────
$renamed  = 0;
$skipped  = 0;
$errors   = [];
$log      = []; // ['type'=>'ok'|'skip'|'err', 'msg'=>'...']

for ($i = 0; $i < count($jpegItems); $i++) {
    $item    = $jpegItems[$i];
    $price   = $prices[$i] ?? null;

    if ($price === null) {
        $errors[] = "אין מחיר עבור קובץ: {$item['fname']}";
        $log[]    = ['type' => 'err', 'msg' => "⚠ אין מחיר עבור: {$item['fname']}"];
        $skipped++;
        continue;
    }

    $newName = $item['dateTimePart'] . ' ' . $price . '.jpeg';
    $newPath = $npDir . '/' . $newName;

    if (file_exists($newPath)) {
        $log[]    = ['type' => 'skip', 'msg' => "דילוג על '{$item['fname']}': הקובץ '{$newName}' כבר קיים"];
        $skipped++;
        continue;
    }

    if (rename($item['fullPath'], $newPath)) {
        $log[] = ['type' => 'ok', 'msg' => "{$item['fname']}  →  {$newName}"];
        $renamed++;
    } else {
        $log[]    = ['type' => 'err', 'msg' => "שגיאה בשינוי שם: {$item['fname']}"];
        $errors[] = "שגיאה בשינוי שם: {$item['fname']}";
        $skipped++;
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Harmonized – שינוי שמות</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 45px; margin: 0; }
        h2 { color: #2575a8; margin-bottom: 9px; font-size: 36px; }
        .sub { color: #777; font-size: 20px; margin-bottom: 36px; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.12); padding: 36px 48px; max-width: 1350px; margin-bottom: 36px; }
        .log-line { font-size: 20px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-family: monospace; }
        .log-line:last-child { border-bottom: none; }
        .ok   { color: #2a7d2a; }
        .skip { color: #b57a00; }
        .err  { color: #c0392b; }
        .summary { padding: 21px 30px; border-radius: 8px; font-size: 21px; font-weight: bold; }
        .summary.all-ok  { background: #e8f5e9; border: 1px solid #a5d6a7; color: #2a7d2a; }
        .summary.has-err { background: #fff8e1; border: 1px solid #ffe082; color: #7a5800; }
        .count-row { display: flex; gap: 42px; margin-top: 18px; font-size: 20px; font-weight: normal; color: #555; }
        .actions { margin-top: 27px; display: flex; gap: 21px; }
        .btn { display: inline-block; padding: 17px 42px; border-radius: 8px; font-size: 22px; cursor: pointer; border: none; text-decoration: none; color: #fff; }
        .btn-next { background: #2575a8; }
        .btn-next:hover { background: #1a5c87; }
        .btn-back { background: #888; }
        .btn-back:hover { background: #666; }
    </style>
</head>
<body>

<h2>NP Harmonized – שינוי שמות קבצי JPEG</h2>
<p class="sub">חנות: <?= htmlspecialchars($shop) ?> | תאריך: <?= htmlspecialchars($date) ?></p>

<div class="card">
    <h3 style="margin-top:0;color:#555">יומן פעולות</h3>
    <?php foreach ($log as $entry): ?>
        <div class="log-line <?= htmlspecialchars($entry['type']) ?>">
            <?= htmlspecialchars($entry['msg']) ?>
        </div>
    <?php endforeach; ?>
    <?php if (empty($log)): ?>
        <div class="log-line skip">לא נמצאו קבצי JPEG תואמים לפורמט WhatsApp</div>
    <?php endif; ?>
</div>

<div class="summary <?= empty($errors) ? 'all-ok' : 'has-err' ?>">
    <?php if (empty($errors)): ?>
        ✅ הושלם. <?= $renamed ?> קבצים שונו בהצלחה.
    <?php else: ?>
        ⚠️ הושלם עם בעיות. <?= $renamed ?> קבצים שונו, <?= $skipped ?> דולגו / נכשלו.
    <?php endif; ?>
    <div class="count-row">
        <span>✔ שונו: <?= $renamed ?></span>
        <?php if ($skipped > 0): ?>
            <span>⚠ דולגו/נכשלו: <?= $skipped ?></span>
        <?php endif; ?>
        <span>סה״כ JPEG: <?= count($jpegItems) ?></span>
        <span>מחירים בקובץ: <?= count($prices) ?></span>
    </div>
</div>

<div class="actions">
    <a class="btn btn-back" href="index.php">חזור לתחילה</a>
    <form id="advanceForm" action="stage_br.php" method="post" style="display:inline">
        <input type="hidden" name="np_dir"       value="<?= htmlspecialchars($npDir) ?>">
        <input type="hidden" name="xlsx_file"    value="<?= htmlspecialchars($xlsxFile) ?>">
        <input type="hidden" name="shop"         value="<?= htmlspecialchars($shop) ?>">
        <input type="hidden" name="date"         value="<?= htmlspecialchars($date) ?>">
        <input type="hidden" name="dept_file"    value="<?= htmlspecialchars($deptFile) ?>">
        <input type="hidden" name="harvest_mode" value="manual">
        <input type="hidden" name="mode"         value="<?= htmlspecialchars($mode) ?>">
        <button type="submit" class="btn btn-next">אישור – עבור לקריאת ברקודים ←</button>
    </form>
</div>
<?php if (empty($errors)) renderFlowAutoAdvance($mode, 'advanceForm'); ?>

</body>
</html>
