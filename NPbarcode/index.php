<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>NP Barcode Reader</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.15); padding: 40px 50px; width: 480px; }
        h2 { margin: 0 0 28px; color: #333; text-align: center; font-size: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #555; font-size: 14px; }
        input[type="file"] { width: 100%; padding: 10px; border: 1px dashed #ccc; border-radius: 6px; font-size: 13px; background: #fafafa; margin-bottom: 20px; box-sizing: border-box; cursor: pointer; }
        button { display: block; width: 100%; padding: 12px; background: #2575a8; color: #fff; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; margin-top: 4px; }
        button:hover { background: #1a5c87; }
        .note { margin-top: 12px; font-size: 12px; color: #888; text-align: center; }
    </style>
</head>
<body>
<div class="card">
    <h2>NP Barcode Reader</h2>
    <form action="process.php" method="post" enctype="multipart/form-data">

        <label for="excel_file">בחר קובץ Excel (NP)</label>
        <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>

        <label for="jpeg_files">בחר תיקיית NP</label>
        <input type="file" id="jpeg_files" name="jpeg_files[]" webkitdirectory multiple required>

        <button type="submit">התחל עיבוד</button>
    </form>
    <p class="note">ניתן לבחור מספר קבצי JPEG בו-זמנית</p>
</div>
</body>
</html>
