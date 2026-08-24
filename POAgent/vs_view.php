<?php
// VS detail view — reached from po_list.php's new "דוח שונות (VS)" column.
// Shows every Variance Statement for a given PO, in delivery order (spec
// §5/§6.3 — VSStore::listForPo() orders by delivery_index, not filename
// timestamp). Read-only, same rendering (poagent_render_vs_detail()) as
// dn_result.php right after import.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
require_once __DIR__ . '/lib/VSStore.php';
$generatorId = poagent_require_generator();

// Same core_name whitelist convention as po_view.php.
$coreName = $_GET['core_name'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]+$/', $coreName)) {
    header('Location: po_list.php');
    exit;
}

$po = POStore::loadByCoreName($coreName);
if ($po === null) {
    header('Location: po_list.php');
    exit;
}

$vsList = VSStore::listForPo($coreName);

poagent_render_head('POAgent – דוחות שונות (VS)', 1000);
?>
<h2>דוחות שונות — הזמנה <?= htmlspecialchars($po['unique_id'] ?? '') ?> (<?= htmlspecialchars($po['supplier_id'] ?? '') ?>)</h2>

<?php if (empty($vsList)): ?>
    <p class="muted">לא נמצאו דוחות שונות עבור הזמנה זו.</p>
<?php else: ?>
    <?php foreach ($vsList as $vs): ?>
        <h3 style="margin-top:32px;color:#555">
            משלוח #<?= (int) $vs['delivery_index'] ?>
            <?php if (!empty($vs['generated_at'])): ?>
                <span class="muted" style="font-size:16px;font-weight:normal">
                    (<?= htmlspecialchars(date('d/m/Y H:i', strtotime($vs['generated_at']))) ?>)
                </span>
            <?php endif; ?>
        </h3>
        <?php poagent_render_vs_detail($vs); ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="row-gap"></div>
<a class="btn secondary" href="po_view.php?core_name=<?= urlencode($coreName) ?>">🔍 צפייה בהזמנה</a>
<div class="row-gap"></div>
<a class="btn secondary" href="po_list.php">📋 חזרה להיסטוריית הזמנות</a>
<?php poagent_render_foot(); ?>
