<?php
// Status/history view (spec §4.1) — read-only, filename-glob backed
// (spec §8.1: "the naming convention itself IS the index").
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
$generatorId = poagent_require_generator();

$showAll = isset($_GET['all']);
$records = POStore::listPOs($showAll ? null : $generatorId);

poagent_render_head('POAgent – היסטוריית הזמנות', 900);
?>
<h2>הזמנות רכש<?= $showAll ? ' (כל המשתמשים)' : ' — ' . htmlspecialchars($generatorId) ?></h2>

<?php if (empty($records)): ?>
    <p class="muted">אין הזמנות עדיין.</p>
<?php else: ?>
<table>
    <tr><th>מס' הזמנה</th><th>ספק</th><th>משתמש</th><th>נוצר ב</th><th>סטטוס</th><th>פריטים</th><th>סה"כ</th></tr>
    <?php foreach ($records as $po): ?>
    <tr>
        <td><?= htmlspecialchars($po['unique_id'] ?? '') ?></td>
        <td><?= htmlspecialchars($po['supplier_id'] ?? '') ?></td>
        <td><?= htmlspecialchars($po['generator_id'] ?? '') ?></td>
        <td><?= htmlspecialchars($po['date_generated'] ?? '') ?></td>
        <td><span class="badge <?= htmlspecialchars($po['status'] ?? '') ?>"><?= htmlspecialchars($po['status'] ?? '') ?></span></td>
        <td><?= count($po['items'] ?? []) ?></td>
        <td><?= number_format(POStore::totalAgorot($po) / 100, 2) ?> ₪</td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<a class="btn secondary" href="po_list.php<?= $showAll ? '' : '?all=1' ?>"><?= $showAll ? 'הצג רק שלי' : 'הצג את כולם' ?></a>
<div class="row-gap"></div>
<a class="btn secondary" href="main_menu.php">חזרה לתפריט</a>
<?php poagent_render_foot(); ?>
