<?php
// PO flow, step 2 — item list with prices + quantities (spec §4.1/§4.2).
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/SupplierStore.php';
$generatorId = poagent_require_generator();

// Reached either via GET (fresh pick from po_supplier.php, qty starts at 0)
// or via POST (bounced back from po_confirm.php's "back" form, carrying the
// previously-entered quantities so they aren't lost — see qtyPrefill below).
$supplierId = $_POST['supplier_id'] ?? $_GET['supplier_id'] ?? '';
if ($supplierId === '' || !SupplierStore::exists($supplierId)) {
    header('Location: po_supplier.php');
    exit;
}

$qtyPrefill = $_POST['qty'] ?? [];
if (!is_array($qtyPrefill)) {
    $qtyPrefill = [];
}

$catalog = SupplierStore::getCatalog($supplierId);

poagent_render_head("POAgent – פריטים: {$supplierId}");
?>
<h2>בחר פריטים וכמויות — <?= htmlspecialchars($supplierId) ?></h2>

<?php if (empty($catalog)): ?>
    <p class="muted">אין פריטים בקטלוג הספק.</p>
<?php else: ?>
<form action="po_confirm.php" method="post">
    <input type="hidden" name="supplier_id" value="<?= htmlspecialchars($supplierId) ?>">
    <table>
        <tr><th>ברקוד</th><th>שם פריט</th><th>מחיר</th><th>כמות</th></tr>
        <?php foreach ($catalog as $item): ?>
        <?php $prefillQty = isset($qtyPrefill[$item['barcode']]) ? (int) $qtyPrefill[$item['barcode']] : 0; ?>
        <tr>
            <td><?= htmlspecialchars($item['barcode']) ?></td>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= number_format($item['price_agorot'] / 100, 2) ?> ₪</td>
            <td>
                <input class="qty" type="number" min="0" step="1" value="<?= $prefillQty ?>"
                       name="qty[<?= htmlspecialchars($item['barcode']) ?>]">
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <button type="submit">המשך לאישור</button>
</form>
<?php endif; ?>

<div class="row-gap"></div>
<a class="btn secondary" href="po_supplier.php">חזרה לבחירת ספק</a>
<?php poagent_render_foot(); ?>
