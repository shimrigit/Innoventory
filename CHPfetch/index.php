<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>CHP Fetch – Price Lookup</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.15); padding: 40px 50px; width: 420px; }
        h2 { margin: 0 0 24px; color: #333; text-align: center; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="file"] { width: 100%; padding: 10px; border: 2px dashed #aaa; border-radius: 6px; background: #fafafa; cursor: pointer; box-sizing: border-box; }
        button { display: block; width: 100%; margin-top: 20px; padding: 12px; background: #2575a8; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
        button:hover { background: #1a5c87; }
        .note { margin-top: 14px; font-size: 12px; color: #888; text-align: center; }
    </style>
</head>
<body>
<div class="card">
    <h2>CHP Fetch</h2>
    <form action="process.php" method="post" enctype="multipart/form-data">
        <label for="excel_file">בחר קובץ Excel עם ברקודים בעמודה A (מ-A2)</label>
        <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
        <button type="submit">התחל עיבוד</button>
    </form>
    <p class="note">הקובץ יעודכן עם שם המוצר, ברקוד, מחיר מקסימלי ומינימלי</p>
</div>
</body>
</html>
