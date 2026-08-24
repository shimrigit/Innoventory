<?php
// PO detail view — reached from po_list.php (history/status view). Shows a
// single PO exactly as po_success.php does right after creation (same
// shared renderer, poagent_render_po_detail() in lib/ui_common.php): id,
// status, supplier, generator, date, and the items list with qty/prices/
// totals — not just the summary row po_list.php shows per PO.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
$generatorId = poagent_require_generator();

$coreName = $_GET['core_name'] ?? '';
// Core names are always <user>_<supplier>_<ddmmyy-His>_PO#####, built
// server-side by POStore::createPO() — reject anything that couldn't be one
// of ours before it ever reaches glob() in loadByCoreName().
$record = (is_string($coreName) && preg_match('/^[A-Za-z0-9_-]+$/', $coreName))
    ? POStore::loadByCoreName($coreName)
    : null;

if (!$record) {
    header('Location: po_list.php');
    exit;
}

poagent_render_head('POAgent – הזמנה ' . ($record['unique_id'] ?? ''));
?>
<h2>הזמנת רכש — <?= htmlspecialchars($record['unique_id'] ?? '') ?></h2>
<?php poagent_render_po_detail($record); ?>

<a class="btn secondary" href="po_list.php">חזרה להיסטוריה</a>
<div class="row-gap"></div>
<a class="btn secondary" href="main_menu.php">חזרה לתפריט</a>
<?php poagent_render_foot(); ?>
