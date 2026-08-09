<?php
$shopsConfig = json_decode(file_get_contents(__DIR__ . '/../configDir/shops_V2.json'), true) ?? [];
$shopNames   = array_column($shopsConfig, 'ShopName');
sort($shopNames);
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Harmonized – התחלה</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.15); padding: 60px 75px; width: 690px; }
        h2 { margin: 0 0 42px; color: #333; text-align: center; font-size: 36px; }
        label { display: block; margin-bottom: 9px; font-weight: bold; color: #555; font-size: 21px; }
        select, input[type="date"], input[type="text"] {
            width: 100%; padding: 15px; border: 1px solid #ccc; border-radius: 8px;
            font-size: 21px; background: #fafafa; margin-bottom: 30px; box-sizing: border-box;
        }
        input[type="text"].letter { width: 90px; text-transform: uppercase; text-align: center; font-size: 27px; font-weight: bold; }
        button { display: block; width: 100%; padding: 18px; background: #2575a8; color: #fff; border: none; border-radius: 8px; font-size: 24px; cursor: pointer; margin-top: 6px; }
        button:hover { background: #1a5c87; }
        .switch-group { display: flex; gap: 15px; margin-bottom: 30px; }
        .switch-opt { flex: 1; }
        .switch-opt input[type="radio"] { display: none; }
        .switch-opt label { display: block; margin: 0; padding: 15px; text-align: center; border: 2px solid #ccc; border-radius: 8px; background: #fafafa; font-size: 20px; font-weight: bold; color: #555; cursor: pointer; }
        .switch-opt input[type="radio"]:checked + label { border-color: #2575a8; background: #e8f1f8; color: #2575a8; }
    </style>
</head>
<body>
<div class="card">
    <h2>NP Harmonized</h2>
    <form action="confirm.php" method="post">

        <label for="date">תאריך עיבוד</label>
        <input type="date" id="date" name="date" value="<?= $today ?>" required>

        <label for="shop">שם חנות</label>
        <select name="shop" id="shop" required>
            <option value="">-- בחר חנות --</option>
            <?php foreach ($shopNames as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="root_letter">אות כונן ראשי</label>
        <input type="text" class="letter" id="root_letter" name="root_letter" value="Z" maxlength="1" required>

        <label>אופן איסוף תמונות</label>
        <div class="switch-group">
            <div class="switch-opt">
                <input type="radio" id="harvest_manual" name="harvest_mode" value="manual">
                <label for="harvest_manual">ידני</label>
            </div>
            <div class="switch-opt">
                <input type="radio" id="harvest_whatsapp" name="harvest_mode" value="whatsapp" checked>
                <label for="harvest_whatsapp">WhatsApp</label>
            </div>
        </div>

        <button type="submit">המשך לאישור</button>
    </form>
</div>
</body>
</html>
