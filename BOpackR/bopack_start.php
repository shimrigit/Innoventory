<?php
/**
 * BOpack Retailomatics - Opening Screen
 *
 * Purpose: Entry point for Back Office pack process
 * Allows user to select shop and process date
 */

// Load shop configuration
$shopsConfigPath = __DIR__ . '/../configDir/shops_V2.json';
if (!file_exists($shopsConfigPath)) {
    die('Error: shops_V2.json configuration file not found.');
}

$shopsConfig = json_decode(file_get_contents($shopsConfigPath), true);
if (!$shopsConfig) {
    die('Error: Failed to parse shops_V2.json configuration file.');
}

// Extract shop names for dropdown
$shopNames = array_map(function($shop) {
    return $shop['ShopName'];
}, $shopsConfig);

// Get today's date for default
$todayDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOpack Retailomatics - Back Office Pack</title>

    <!-- Select2 CSS for searchable dropdown -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
            text-align: center;
        }

        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        select, input[type="date"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
            direction: rtl;
        }

        select:focus, input[type="date"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .select2-container {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--single {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            height: 48px;
            padding: 8px 15px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
            padding-left: 0;
            padding-right: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #667eea;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
            color: #2196F3;
        }

        .required {
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 BOpack Retailomatics</h1>
        <p class="subtitle">Back Office Pack - Generate ERP Upload Files</p>

        <div class="info-box">
            <strong>תהליך BOpack:</strong>
            תהליך זה יוצר קבצים להעלאה למערכת ה-ERP מקבצי CL, PC ו-NP עבור חנות ותאריך נבחרים.
        </div>

        <form method="POST" action="process_bopack_r.php">
            <div class="form-group">
                <label for="shopName">
                    שם החנות <span class="required">*</span>
                </label>
                <select id="shopName" name="shopName" required>
                    <option value="">בחר חנות...</option>
                    <?php foreach ($shopNames as $shopName): ?>
                        <option value="<?= htmlspecialchars($shopName) ?>">
                            <?= htmlspecialchars($shopName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="processDate">
                    תאריך עיבוד <span class="required">*</span>
                </label>
                <input
                    type="date"
                    id="processDate"
                    name="processDate"
                    value="<?= $todayDate ?>"
                    required
                >
            </div>

            <button type="submit" class="btn-submit">
                🚀 התחל תהליך BOpack
            </button>
        </form>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2 for searchable dropdown
            $('#shopName').select2({
                placeholder: 'בחר חנות...',
                dir: 'rtl',
                language: {
                    noResults: function() {
                        return "לא נמצאו תוצאות";
                    },
                    searching: function() {
                        return "מחפש...";
                    }
                }
            });
        });
    </script>
</body>
</html>
