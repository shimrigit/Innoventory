<?php
// Screen 2 — main menu (spec §4.1). "Upload DN" is out of scope for this
// phase (Digital Note ingestion is built in a later phase) so it's shown
// disabled rather than omitted, so the eventual flow is visible in the UI.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
$generatorId = poagent_require_generator();

poagent_render_head('POAgent – תפריט ראשי');
?>
<h2>שלום, <?= htmlspecialchars($generatorId) ?></h2>

<a class="btn" href="po_supplier.php">➕ צור הזמנת רכש (PO)</a>
<div class="row-gap"></div>
<span class="btn disabled" title="ייבנה בשלב הבא">📷 העלה תעודת משלוח (בקרוב)</span>
<div class="row-gap"></div>
<a class="btn secondary" href="po_list.php">📋 היסטוריית הזמנות</a>
<div class="row-gap"></div>
<a class="btn secondary" href="index.php">החלף משתמש</a>

<?php poagent_render_foot(); ?>
