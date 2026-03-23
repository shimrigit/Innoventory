<?php
define('CONFIG_DIR', __DIR__ . '/../configDir');

// Discover shops that have a _Departments.json file
$shopNames = [];
foreach (glob(CONFIG_DIR . '/*_Departments.json') as $file) {
    $base = basename($file, '_Departments.json');
    $shopNames[] = $base;
}
sort($shopNames);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Department Classifier</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.15); padding: 40px 50px; width: 460px; }
        h2 { margin: 0 0 28px; color: #333; text-align: center; font-size: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; font-size: 14px; }
        select, input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; background: #fafafa; margin-bottom: 20px; box-sizing: border-box; }
        input[type="file"] { border-style: dashed; cursor: pointer; }
        button { display: block; width: 100%; padding: 12px; background: #2575a8; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; margin-top: 4px; }
        button:hover { background: #1a5c87; }
        .note { margin-top: 12px; font-size: 12px; color: #888; text-align: center; }
    </style>
</head>
<body>
<div class="card">
    <h2>NP Department Classifier</h2>
    <form action="process.php" method="post" enctype="multipart/form-data">

        <label for="shop">בחר חנות</label>
        <select name="shop" id="shop" required>
            <option value="">-- בחר חנות --</option>
            <?php foreach ($shopNames as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="np_file">בחר קובץ NP (Excel)</label>
        <input type="file" id="np_file" name="np_file" accept=".xlsx,.xls" required>

        <button type="submit">סווג מחלקות</button>
    </form>
    <p class="note">המוצרים מעמודה B יסווגו לפי מחלקות החנות הנבחרת</p>
</div>
</body>
</html>
