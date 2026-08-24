<?php
// Status/history view (spec §4.1) — read-only, filename-glob backed
// (spec §8.1: "the naming convention itself IS the index").
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
require_once __DIR__ . '/lib/DNStore.php';
require_once __DIR__ . '/lib/VSStore.php';
$generatorId = poagent_require_generator();

// Default to showing ALL users' POs (helps with debugging during the demo
// build) — pass ?mine=1 to narrow to just the current session's user.
// TODO: revisit after the demo, when real per-user separation matters.
$showAll = !isset($_GET['mine']);
$records = POStore::listPOs($showAll ? null : $generatorId);

poagent_render_head('POAgent – היסטוריית הזמנות', 1050);
?>
<h2>הזמנות רכש<?= $showAll ? ' (כל המשתמשים)' : ' — ' . htmlspecialchars($generatorId) ?></h2>

<?php if (empty($records)): ?>
    <p class="muted">אין הזמנות עדיין.</p>
<?php else: ?>
<table>
    <tr>
        <th>מס' הזמנה</th><th>ספק</th><th>משתמש</th><th>נוצר ב</th><th>סטטוס</th><th>פריטים</th><th>סה"כ</th>
        <th>תעודת משלוח (DN)</th><th>דוח שונות (VS)</th><th></th>
    </tr>
    <?php foreach ($records as $po): ?>
    <?php
        $coreName = $po['core_name'] ?? '';
        $dnCount = count(DNStore::listForPo($coreName));
        $vsList = VSStore::listForPo($coreName);
        $vsCount = count($vsList);
        $vsHasVariance = array_reduce($vsList, fn($carry, $v) => $carry || ($v['status'] ?? '') === 'variance', false);
    ?>
    <tr>
        <td><?= htmlspecialchars($po['unique_id'] ?? '') ?></td>
        <td><?= htmlspecialchars($po['supplier_id'] ?? '') ?></td>
        <td><?= htmlspecialchars($po['generator_id'] ?? '') ?></td>
        <td><?= htmlspecialchars($po['date_generated'] ?? '') ?></td>
        <td><span class="badge <?= htmlspecialchars($po['status'] ?? '') ?>"><?= htmlspecialchars($po['status'] ?? '') ?></span></td>
        <td><?= count($po['items'] ?? []) ?></td>
        <td><?= number_format(POStore::totalAgorot($po) / 100, 2) ?> ₪</td>
        <td>
            <?php if ($dnCount > 0): ?>
                <a href="dn_view.php?core_name=<?= urlencode($coreName) ?>">📷 <?= $dnCount ?></a>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($vsCount > 0): ?>
                <a href="vs_view.php?core_name=<?= urlencode($coreName) ?>">
                    <?= $vsHasVariance ? '⚠' : '✔' ?> <?= $vsCount ?>
                </a>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </td>
        <td><a href="po_view.php?core_name=<?= urlencode($coreName) ?>">🔍 צפייה</a></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<a class="btn secondary" href="po_list.php<?= $showAll ? '?mine=1' : '' ?>"><?= $showAll ? 'הצג רק שלי' : 'הצג את כולם' ?></a>
<div class="row-gap"></div>
<a class="btn secondary" href="main_menu.php">חזרה לתפריט</a>
<?php poagent_render_foot(); ?>
