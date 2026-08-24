<?php
// DN flow, step 3 (spec §9 steps 3–4) — copies the chosen photo into
// DNdir/, OCRs it in one vision call, and sanity-checks the result, then
// hands off to dn_review.php (Stage 3's human-in-the-loop correction
// screen) rather than finalizing directly. An OCR misread and a genuine
// supplier variance would otherwise look identical to the Diff Engine — the
// Review screen is what tells them apart.
//
// Splitting the flow here also means refreshing dn_review.php never
// re-runs OCR (cost/idempotency, not just POST/redirect/GET safety).
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
require_once __DIR__ . '/lib/DNStore.php';
require_once __DIR__ . '/lib/DNOcr.php';
require_once __DIR__ . '/lib/DNSanity.php';
$generatorId = poagent_require_generator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['po_core_name']) || empty($_POST['source_path'])) {
    header('Location: dn_select_po.php');
    exit;
}

$poCoreName = $_POST['po_core_name'];
if (!preg_match('/^[A-Za-z0-9_-]+$/', $poCoreName)) {
    header('Location: dn_select_po.php');
    exit;
}
$po = POStore::loadByCoreName($poCoreName);
if ($po === null) {
    header('Location: dn_select_po.php');
    exit;
}

$sourcePath = $_POST['source_path'];

function poagent_dn_error(string $message, string $poCoreName): void
{
    poagent_render_head('POAgent – שגיאה בעיבוד תעודת המשלוח');
    ?>
    <h2>⚠️ שגיאה בעיבוד תעודת המשלוח</h2>
    <p class="muted"><?= htmlspecialchars($message) ?></p>
    <a class="btn secondary" href="dn_browse.php?po_core_name=<?= urlencode($poCoreName) ?>">חזרה לבחירת תמונה</a>
    <?php
    poagent_render_foot();
    exit;
}

// Step 1 — copy the image into DNdir/.
try {
    $imported = DNStore::importImage($poCoreName, $sourcePath);
} catch (Throwable $e) {
    poagent_dn_error($e->getMessage(), $poCoreName);
}

// Steps 2–3 — OCR + sanity check. The image is already saved at this point
// even if OCR fails, so nothing is lost — just re-import to retry.
try {
    $ocrRaw = DNOcr::extract($imported['image_path']);
} catch (Throwable $e) {
    poagent_dn_error('התמונה נשמרה, אך שלב ה-OCR נכשל: ' . $e->getMessage(), $poCoreName);
}
$sanity = DNSanity::check($ocrRaw);

$_SESSION['poagent_dn_draft'] = [
    'po_core_name'      => $poCoreName,
    'dn_core_name'      => $imported['dn_core_name'],
    'image_filename'    => $imported['image_filename'],
    'supplier_name_ocr' => $ocrRaw['supplier_name'],
    'dn_number_ocr'     => $ocrRaw['dn_number'],
    'dn_date_ocr'       => $ocrRaw['dn_date'],
    'dn_total'          => $sanity['dn_total'], // nullable — see DNOcr::extract()
    'items'             => $sanity['items'],
];

header('Location: dn_review.php');
exit;
