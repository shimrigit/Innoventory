<?php
// Screen 2 — main menu (spec §4.1). "Upload DN" enabled as of the DN
// ingestion build (spec §9 steps 3+) — see dn_select_po.php onward.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
$generatorId = poagent_require_generator();

poagent_render_head('POAgent – תפריט ראשי');
?>
<h2>שלום, <?= htmlspecialchars($generatorId) ?></h2>

<a class="btn" href="po_supplier.php">➕ צור הזמנת רכש (PO)</a>
<div class="row-gap"></div>
<a class="btn" href="dn_select_po.php">📷 העלה תעודת משלוח</a>
<div class="row-gap"></div>
<a class="btn secondary" href="po_list.php">📋 היסטוריית הזמנות</a>
<div class="row-gap"></div>
<a class="btn secondary" href="index.php">החלף משתמש</a>

<?php poagent_render_foot(); ?>
