<?php
// PO flow, step 5 — confirmation screen. Reads the one-time flash set by po_create.php.
session_start();
require_once __DIR__ . '/lib/ui_common.php';
$generatorId = poagent_require_generator();

$record = $_SESSION['poagent_last_po'] ?? null;
unset($_SESSION['poagent_last_po']);

if (!$record) {
    header('Location: po_list.php');
    exit;
}

poagent_render_head('POAgent – הזמנה נוצרה');
?>
<h2>✅ הזמנת רכש נוצרה</h2>
<p><strong>מספר הזמנה:</strong> <?= htmlspecialchars($record['unique_id']) ?></p>
<p><strong>שם קובץ (core name):</strong><br><span class="muted"><?= htmlspecialchars($record['core_name']) ?></span></p>
<p><strong>ספק:</strong> <?= htmlspecialchars($record['supplier_id']) ?> &nbsp; <strong>נוצר ע"י:</strong> <?= htmlspecialchars($record['generator_id']) ?></p>

<table>
    <tr><th>ברקוד</th><th>שם פריט</th><th>כמות</th><th>מחיר יח'</th></tr>
    <?php foreach ($record['items'] as $line): ?>
    <tr>
        <td><?= htmlspecialchars($line['barcode']) ?></td>
        <td><?= htmlspecialchars($line['name']) ?></td>
        <td><?= (int) $line['qty'] ?></td>
        <td><?= number_format($line['unit_price_agorot'] / 100, 2) ?> ₪</td>
    </tr>
    <?php endforeach; ?>
</table>

<a class="btn" href="po_supplier.php">➕ הזמנה נוספת</a>
<div class="row-gap"></div>
<a class="btn secondary" href="po_list.php">📋 היסטוריית הזמנות</a>
<div class="row-gap"></div>
<a class="btn secondary" href="main_menu.php">חזרה לתפריט</a>
<?php poagent_render_foot(); ?>
