<?php
// DN detail view — reached from po_list.php's new "תעודת משלוח (DN)" column.
// Shows every DN photo + its OCR/reviewed data (plus its total-verification
// check, sourced from the matching VS — see below) for a given PO, oldest
// first (a PO can have more than one delivery — spec §5). Each delivery is
// its own data-left/photo-right split (same as dn_result.php) — no
// lightbox, the large zoomable photo panel stays on the page next to the
// data it's being compared against. Read-only.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/POStore.php';
require_once __DIR__ . '/lib/DNStore.php';
require_once __DIR__ . '/lib/VSStore.php';
$generatorId = poagent_require_generator();

// Same core_name whitelist convention as po_view.php — blocks path
// traversal/glob-wildcard shapes before they ever reach glob().
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

$dns = DNStore::listForPo($coreName);

// Total verification (DiffEngine's total_check) lives on the VS, not the DN
// record — a DN has no PO-comparison context of its own. Match each DN to
// its own VS by dn_reference (they're always created together in the same
// dn_confirm.php call) so this page can show the same numbers vs_view.php
// does, from the same single source of truth.
$vsByDnReference = [];
foreach (VSStore::listForPo($coreName) as $vs) {
    $vsByDnReference[$vs['dn_reference'] ?? ''] = $vs;
}

poagent_render_head('POAgent – תעודות משלוח', 1700);
?>
<h2>תעודות משלוח — הזמנה <?= htmlspecialchars($po['unique_id'] ?? '') ?> (<?= htmlspecialchars($po['supplier_id'] ?? '') ?>)</h2>

<?php if (empty($dns)): ?>
    <p class="muted">לא נמצאו תעודות משלוח עבור הזמנה זו.</p>
<?php else: ?>
    <?php foreach ($dns as $i => $dn): ?>
        <?php $imageUrl = 'DNdir/' . rawurlencode($dn['image_filename'] ?? ''); ?>
        <div style="display:flex; flex-wrap:wrap; gap:28px; align-items:flex-start; margin-top:32px;">

            <div style="flex:1 1 420px; position:sticky; top:20px;">
                <?php poagent_render_zoom_panel($imageUrl, 'תמונת תעודת משלוח', '72vh'); ?>
            </div>

            <div style="flex:1 1 520px; min-width:0;">
                <h3 style="margin-top:0;color:#555">
                    תעודה #<?= $i + 1 ?>
                    <?php if (!empty($dn['extracted_at'])): ?>
                        <span class="muted" style="font-size:16px;font-weight:normal">
                            (<?= htmlspecialchars(date('d/m/Y H:i', strtotime($dn['extracted_at']))) ?>)
                        </span>
                    <?php endif; ?>
                </h3>
                <?php poagent_render_dn_detail($dn); ?>
                <?php $matchingVs = $vsByDnReference[$dn['dn_core_name'] ?? ''] ?? null; ?>
                <?php if ($matchingVs !== null && isset($matchingVs['total_check'])): ?>
                    <?php poagent_render_total_check($matchingVs['total_check']); ?>
                <?php else: ?>
                    <p class="muted">אין נתוני בדיקת סה"כ עבור תעודה זו.</p>
                <?php endif; ?>
            </div>

        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="row-gap"></div>
<a class="btn secondary" href="po_view.php?core_name=<?= urlencode($coreName) ?>">🔍 צפייה בהזמנה</a>
<div class="row-gap"></div>
<a class="btn secondary" href="po_list.php">📋 חזרה להיסטוריית הזמנות</a>
<?php poagent_render_foot(); ?>
