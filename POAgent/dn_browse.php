<?php
// DN flow, step 2 (spec §9 step 3) — server-side folder browser.
//
// A plain <input type=file> can't be made to default to a given folder
// (browsers deliberately don't let a page preset the OS picker's starting
// directory) — so instead this reads the local filesystem directly, the
// same way every other POAgent DataStore adapter does, since this is a
// single-user local app (spec §8.9 — no security hardening needed at this
// stage). Defaults to DNStore::defaultBrowseDir() (Desktop\DN pictures);
// "שנה תיקייה" lets the user jump anywhere else on the machine.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
require_once __DIR__ . '/lib/DNStore.php';
$generatorId = poagent_require_generator();

$poCoreName = $_GET['po_core_name'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]+$/', $poCoreName)) {
    header('Location: dn_select_po.php');
    exit;
}
$po = POStore::loadByCoreName($poCoreName);
if ($po === null) {
    header('Location: dn_select_po.php');
    exit;
}

// Resolve the folder to list: an explicit ?dir= if it's a real, readable
// directory, otherwise fall back to the computed default.
$requestedDir = $_GET['dir'] ?? '';
$currentDir = null;
if ($requestedDir !== '') {
    $real = realpath($requestedDir);
    if ($real !== false && is_dir($real)) {
        $currentDir = $real;
    }
}
$usedDefault = false;
$badRequestedDir = ($requestedDir !== '' && $currentDir === null) ? $requestedDir : null;
if ($currentDir === null) {
    $currentDir = DNStore::defaultBrowseDir();
    $usedDefault = true;
}

$entries = is_readable($currentDir) ? (scandir($currentDir) ?: []) : [];
$dirs = [];
$files = [];
$allowedExt = ['jpg', 'jpeg', 'png'];
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') continue;
    $full = $currentDir . DIRECTORY_SEPARATOR . $entry;
    if (is_dir($full)) {
        $dirs[] = $entry;
    } else {
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt, true)) {
            $files[] = $entry;
        }
    }
}
sort($dirs);
sort($files);
$parentDir = dirname($currentDir);

poagent_render_head('POAgent – בחירת תמונת תעודת משלוח', 900);
?>
<h2>בחר תמונה עבור <?= htmlspecialchars($po['unique_id'] ?? '') ?> (<?= htmlspecialchars($po['supplier_id'] ?? '') ?>)</h2>

<?php if ($badRequestedDir !== null): ?>
    <p class="muted">⚠️ התיקייה "<?= htmlspecialchars($badRequestedDir) ?>" לא נמצאה — מוצגת תיקיית ברירת המחדל.</p>
<?php endif; ?>

<form method="get" action="dn_browse.php" style="display:flex; gap:12px; align-items:flex-end;">
    <input type="hidden" name="po_core_name" value="<?= htmlspecialchars($poCoreName) ?>">
    <div style="flex:1">
        <label for="dir">שנה תיקייה</label>
        <input type="text" id="dir" name="dir" value="<?= htmlspecialchars($currentDir) ?>" style="margin-bottom:0">
    </div>
    <button type="submit" style="width:auto; padding:13px 28px; margin-bottom:22px;">מעבר</button>
</form>

<p class="muted">
    תיקייה נוכחית: <?= htmlspecialchars($currentDir) ?><?= $usedDefault ? ' (ברירת מחדל)' : '' ?>
    <?php if ($parentDir !== $currentDir): ?>
        &nbsp;|&nbsp; <a href="dn_browse.php?po_core_name=<?= urlencode($poCoreName) ?>&dir=<?= urlencode($parentDir) ?>">⬆ תיקייה למעלה</a>
    <?php endif; ?>
</p>

<?php if (empty($dirs) && empty($files)): ?>
    <p class="muted">התיקייה ריקה, או שאין בה תמונות/תת-תיקיות.</p>
<?php endif; ?>

<?php foreach ($dirs as $d): ?>
    <p>📁 <a href="dn_browse.php?po_core_name=<?= urlencode($poCoreName) ?>&dir=<?= urlencode($currentDir . DIRECTORY_SEPARATOR . $d) ?>"><?= htmlspecialchars($d) ?></a></p>
<?php endforeach; ?>

<?php foreach ($files as $f): ?>
    <form method="post" action="dn_import.php" style="display:flex; align-items:center; gap:14px; margin-bottom:10px;">
        <span style="flex:1">🖼️ <?= htmlspecialchars($f) ?></span>
        <input type="hidden" name="po_core_name" value="<?= htmlspecialchars($poCoreName) ?>">
        <input type="hidden" name="source_path" value="<?= htmlspecialchars($currentDir . DIRECTORY_SEPARATOR . $f) ?>">
        <button type="submit" style="width:auto; padding:8px 22px;">ייבוא ←</button>
    </form>
<?php endforeach; ?>

<div class="row-gap"></div>
<a class="btn secondary" href="dn_select_po.php">חזרה לבחירת הזמנה</a>
<?php poagent_render_foot(); ?>
