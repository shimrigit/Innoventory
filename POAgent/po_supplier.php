<?php
// PO flow, step 1 — supplier list (spec §4.1/§4.2).
session_start();
require_once __DIR__ . '/lib/ui_common.php';
require_once __DIR__ . '/lib/SupplierStore.php';
$generatorId = poagent_require_generator();

$suppliers = SupplierStore::listSuppliers();

poagent_render_head('POAgent – בחירת ספק');
?>
<h2>בחר ספק</h2>

<?php if (empty($suppliers)): ?>
    <p class="muted">לא נמצאו ספקים ב-SuppliersDB.</p>
<?php else: ?>
<form action="po_items.php" method="get">
    <label for="supplier_id">ספק</label>
    <select name="supplier_id" id="supplier_id" required>
        <option value="">-- בחר ספק --</option>
        <?php foreach ($suppliers as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">המשך לבחירת פריטים</button>
</form>
<?php endif; ?>

<div class="row-gap"></div>
<a class="btn secondary" href="main_menu.php">חזרה לתפריט</a>
<?php poagent_render_foot(); ?>
