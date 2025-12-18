<?php
/**
 * CHP Price Search Interface
 * Web UI for searching product prices using headless browser scraper
 */

$results = null;
$error = null;
$barcode = '';
$city = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $barcode = trim($_POST['barcode'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if (empty($barcode) || empty($city)) {
        $error = 'נא למלא ברקוד ועיר';
    } else {
        require_once __DIR__ . '/chp_headless_client.php';

        // Capture output buffer to see scraper console output
        ob_start();
        $client = new CHPHeadlessClient();
        $results = $client->searchPrices($barcode, $city);
        $scraperOutput = ob_get_clean();

        // Debug: log what we got back
        error_log("CHP Search Results: " . print_r($results, true));
        error_log("Scraper Console Output: " . $scraperOutput);

        if (!$results) {
            $error = '*** DEBUG VERSION 2024 *** CHP returned FALSE';
            // Always show scraper debug output (even if empty)
            $error .= '<br><br><strong>Scraper Output Length: ' . strlen($scraperOutput) . ' chars</strong>';
            $error .= '<br><details open style="margin-top: 10px; background: #fff3cd; padding: 10px;"><summary style="cursor: pointer; font-weight: bold;">📋 Click to see scraper output</summary><pre style="text-align: left; direction: ltr; font-size: 11px; max-height: 300px; overflow: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd; white-space: pre-wrap;">';
            $error .= empty($scraperOutput) ? '[No output from scraper - check if Node.js is installed]' : htmlspecialchars($scraperOutput);
            $error .= '</pre></details>';
        } elseif (empty($results['prices'])) {
            // Product found but no prices - show product info but with a warning
            $error = 'לא נמצאו מחירים זמינים בחנויות בקריית אונו';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>חיפוש מחירים - CHP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .product-info-banner {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 20px;
            text-align: center;
            border-right: 5px solid #667eea;
        }

        .product-info-banner .product-text {
            font-size: 1.3em;
            color: #2c3e50;
            font-weight: 500;
            line-height: 1.6;
        }

        .product-info-banner .product-text strong {
            color: #667eea;
        }

        .search-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 1.1em;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }

        .form-group input {
            padding: 15px;
            font-size: 1.1em;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-button {
            padding: 15px 30px;
            font-size: 1.2em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .search-button:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fee;
            border: 2px solid #fcc;
            border-radius: 8px;
            padding: 15px;
            color: #c33;
            text-align: center;
            font-size: 1.1em;
            margin-bottom: 20px;
        }

        .results-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .results-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }

        .results-header h2 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .product-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .product-info p {
            margin: 5px 0;
            font-size: 1.1em;
        }

        .product-info strong {
            color: #667eea;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .results-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .results-table th {
            padding: 15px;
            text-align: right;
            font-size: 1.1em;
            font-weight: bold;
        }

        .results-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }

        .results-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .results-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .results-table tbody tr:nth-child(even):hover {
            background-color: #f0f0f0;
        }

        .results-table td {
            padding: 12px 15px;
            text-align: right;
        }

        .price-cell {
            font-weight: bold;
            color: #27ae60;
            font-size: 1.2em;
        }

        .promotion-cell {
            color: #e74c3c;
            font-weight: bold;
        }

        .chain-cell {
            font-weight: 600;
            color: #2c3e50;
        }

        .best-price {
            background-color: #d4edda !important;
            border-right: 4px solid #28a745;
        }

        .stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            flex-wrap: wrap;
            gap: 15px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            flex: 1;
            min-width: 150px;
        }

        .stat-box .value {
            font-size: 2em;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }

        .stat-box .label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .results-table {
                font-size: 0.9em;
            }

            .results-table th,
            .results-table td {
                padding: 8px;
            }

            .header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 חיפוש מחירים בסופר</h1>
            <p>השווה מחירים בין רשתות השיווק השונות</p>
        </div>

        <?php if ($results && !empty($results['productInfo'])): ?>
            <div class="product-info-banner">
                <div class="product-text">
                    <?= htmlspecialchars($results['productInfo']) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="search-card">
            <form method="POST" class="search-form">
                <div class="form-group">
                    <label for="city">עיר:</label>
                    <input
                        type="text"
                        id="city"
                        name="city"
                        placeholder="לדוגמה: קרית אונו"
                        value="<?= htmlspecialchars($city) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="barcode">ברקוד מוצר:</label>
                    <input
                        type="text"
                        id="barcode"
                        name="barcode"
                        placeholder="לדוגמה: 7290000554532"
                        value="<?= htmlspecialchars($barcode) ?>"
                        required
                    >
                </div>

                <button type="submit" name="search" class="search-button">
                    🔎 בדוק מחירים
                </button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                ⚠️ <?= $error ?>
                <?php if ($results): ?>
                    <br><small style="color: #666; margin-top: 10px; display: block;">
                        Debug: productInfo = "<?= htmlspecialchars($results['productInfo'] ?? 'NOT SET') ?>" |
                        productName = "<?= htmlspecialchars($results['productName'] ?? 'NOT SET') ?>" |
                        prices count = <?= count($results['prices'] ?? []) ?>
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($results && !empty($results['prices'])): ?>
            <div class="results-card">
                <div class="results-header">
                    <h2>תוצאות החיפוש</h2>
                    <?php if (!empty($results['productName'])): ?>
                        <div class="product-info">
                            <p><strong>שם המוצר:</strong> <?= htmlspecialchars($results['productName']) ?></p>
                            <p><strong>ברקוד:</strong> <?= htmlspecialchars($barcode) ?></p>
                            <p><strong>עיר:</strong> <?= htmlspecialchars($city) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php
                // Calculate statistics
                $prices = array_filter(
                    array_map(function($item) {
                        return is_numeric($item['price']) ? (float)$item['price'] : null;
                    }, $results['prices'])
                );

                $minPrice = !empty($prices) ? min($prices) : 0;
                $maxPrice = !empty($prices) ? max($prices) : 0;
                $avgPrice = !empty($prices) ? array_sum($prices) / count($prices) : 0;
                $numStores = count($results['prices']);
                ?>

                <div class="stats">
                    <div class="stat-box">
                        <span class="value"><?= number_format($minPrice, 2) ?> ₪</span>
                        <span class="label">מחיר הזול ביותר</span>
                    </div>
                    <div class="stat-box">
                        <span class="value"><?= number_format($avgPrice, 2) ?> ₪</span>
                        <span class="label">מחיר ממוצע</span>
                    </div>
                    <div class="stat-box">
                        <span class="value"><?= number_format($maxPrice, 2) ?> ₪</span>
                        <span class="label">מחיר היקר ביותר</span>
                    </div>
                    <div class="stat-box">
                        <span class="value"><?= $numStores ?></span>
                        <span class="label">חנויות נמצאו</span>
                    </div>
                </div>

                <table class="results-table">
                    <thead>
                        <tr>
                            <th>רשת</th>
                            <th>שם החנות</th>
                            <th>כתובת</th>
                            <th>מבצע</th>
                            <th>מחיר</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['prices'] as $item): ?>
                            <?php
                            $isBestPrice = is_numeric($item['price']) && (float)$item['price'] == $minPrice;
                            ?>
                            <tr <?= $isBestPrice ? 'class="best-price"' : '' ?>>
                                <td class="chain-cell"><?= htmlspecialchars($item['chain'] ?? '') ?></td>
                                <td><?= htmlspecialchars($item['store'] ?? '') ?></td>
                                <td><?= htmlspecialchars($item['address'] ?? '') ?></td>
                                <td class="promotion-cell"><?= htmlspecialchars($item['promotion'] ?? '') ?></td>
                                <td class="price-cell">
                                    <?php if (is_numeric($item['price'])): ?>
                                        <?= number_format((float)$item['price'], 2) ?> ₪
                                    <?php else: ?>
                                        <?= htmlspecialchars($item['price']) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
