<?php
// DN flow, step 5 (spec §9 step 5, tail) — takes the (possibly
// human-corrected) rows posted from dn_review.php, re-validates them the
// same way DNSanity::check() would (never trust posted values blindly,
// even the user's own corrections — matches this codebase's
// always-re-derive-server-side convention, see po_confirm.php/po_create.php),
// finalizes the DN record, and hands off to DNPipeline::finalizeDelivery()
// for the Diff Engine + VS + status transition.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
require_once __DIR__ . '/lib/DNPipeline.php';
$generatorId = poagent_require_generator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['po_core_name']) || empty($_POST['dn_core_name'])) {
    header('Location: dn_select_po.php');
    exit;
}

$poCoreName = $_POST['po_core_name'];
$dnCoreName = $_POST['dn_core_name'];
if (!preg_match('/^[A-Za-z0-9_-]+$/', $poCoreName) || !preg_match('/^[A-Za-z0-9_-]+$/', $dnCoreName)) {
    header('Location: dn_select_po.php');
    exit;
}

$po = POStore::loadByCoreName($poCoreName);
$draft = $_SESSION['poagent_dn_draft'] ?? null;
if ($po === null || $draft === null || $draft['dn_core_name'] !== $dnCoreName) {
    unset($_SESSION['poagent_dn_draft']);
    header('Location: dn_select_po.php');
    exit;
}

// Re-validate every posted row — same rules as DNSanity::check(), applied
// to whatever the user left in the form (unedited OCR value or a hand
// correction, treated identically). Rows checked "מחק" or left fully blank
// (the spare rows) are dropped silently.
$postedItems = $_POST['items'] ?? [];
$items = [];
foreach ($postedItems as $row) {
    if (!empty($row['delete'])) {
        continue;
    }
    $barcode = trim((string) ($row['barcode'] ?? ''));
    if ($barcode === '') {
        continue;
    }
    $qtyRaw = $row['qty'] ?? '';
    $priceRaw = $row['unit_price'] ?? '';

    $barcodeInvalid = !ctype_digit($barcode) || strlen($barcode) > 13;
    $qtyInvalid = !is_numeric($qtyRaw);
    $priceInvalid = !is_numeric($priceRaw);

    $items[] = [
        'barcode'         => $barcode,
        'name'            => trim((string) ($row['name'] ?? '')),
        'qty'             => $qtyInvalid ? 0 : (float) $qtyRaw,
        'unit_price'      => $priceInvalid ? 0.0 : (float) $priceRaw,
        'barcode_invalid' => $barcodeInvalid,
        'qty_invalid'     => $qtyInvalid,
        'price_invalid'   => $priceInvalid,
    ];
}

$allValid = !array_reduce(
    $items,
    fn($carry, $it) => $carry || $it['barcode_invalid'] || $it['qty_invalid'] || $it['price_invalid'],
    false
);

// Empty/non-numeric posted total means "no total on this document" — stays
// null, same as an OCR miss, rather than false-flagging the total checks.
$dnTotalRaw = trim((string) ($_POST['dn_total'] ?? ''));
$dnTotal = ($dnTotalRaw !== '' && is_numeric($dnTotalRaw)) ? (float) $dnTotalRaw : null;

$dnRecord = [
    'dn_core_name'      => $dnCoreName,
    'po_core_name'      => $poCoreName,
    'image_filename'    => (string) ($_POST['image_filename'] ?? ''),
    'supplier_name_ocr' => (string) ($_POST['supplier_name_ocr'] ?? ''),
    'dn_number_ocr'     => (string) ($_POST['dn_number_ocr'] ?? ''),
    'dn_date_ocr'       => (string) ($_POST['dn_date_ocr'] ?? ''),
    'dn_total'          => $dnTotal,
    'extracted_at'      => date('c'),
    'reviewed'          => true, // went through dn_review.php, not auto-confirmed
    'sanity_ok'         => $allValid,
    'items'             => $items,
];

$result = DNPipeline::finalizeDelivery($po, $dnRecord);

unset($_SESSION['poagent_dn_draft']);
$_SESSION['poagent_last_dn'] = $result;
header('Location: dn_result.php');
exit;
