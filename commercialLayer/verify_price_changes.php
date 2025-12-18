<?php
/**
 * Price Change Verification Interface
 * Split-screen view: PC file data (left) + CHP price search (right)
 */

session_start();

// Load required files
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/chp_headless_client.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Get parameters from session or query
$pcFileName = $_GET['pcFile'] ?? $_SESSION['pcFileName'] ?? null;
$shopName = $_GET['shop'] ?? $_SESSION['shopName'] ?? 'CountryMZ';

if (!$pcFileName) {
    die('Error: No PC file specified');
}

// Load shop configuration for default city
$shopsConfig = json_decode(file_get_contents(__DIR__ . '/../configDir/shops_V2.json'), true);
$shopConfig = null;
foreach ($shopsConfig as $shop) {
    if ($shop['ShopName'] === $shopName) {
        $shopConfig = $shop;
        break;
    }
}

$defaultCity = $shopConfig['shopDefaultCity'] ?? 'תל אביב';

// Load PC file data
$pcFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'commercial_invoice_files' . DIRECTORY_SEPARATOR . $pcFileName;
if (!file_exists($pcFilePath)) {
    die('Error: PC file not found: ' . $pcFilePath);
}

$spreadsheet = IOFactory::load($pcFilePath);
$sheet = $spreadsheet->getActiveSheet();
$pcData = $sheet->toArray();

// Track which rows have modified Rec Mrgn (light orange background)
$modifiedRows = [];
// Track which rows have barcode cells with "Not Found" (bold borders)
$notFoundBarcodeRows = [];
$headers = $pcData[0];
$colRecMrgnIdx = array_search('Rec Mrgn', $headers);
$colPriceDiffIdx = array_search('PriceDiff', $headers);

if ($colRecMrgnIdx !== false) {
    for ($i = 2; $i <= count($pcData); $i++) { // Start from row 2 (skip header)
        $cell = $sheet->getCellByColumnAndRow($colRecMrgnIdx + 1, $i);
        $fillColor = $cell->getStyle()->getFill()->getStartColor()->getARGB();

        // Check if cell has light orange background (FFFFD700)
        if ($fillColor === 'FFFFD700') {
            $modifiedRows[] = $i;
        }
    }
}

// Track barcode rows with "Not Found" in PriceDiff column
if ($colPriceDiffIdx !== false) {
    for ($i = 2; $i <= count($pcData); $i++) { // Start from row 2 (skip header)
        $priceDiffCell = $sheet->getCellByColumnAndRow($colPriceDiffIdx + 1, $i);
        $priceDiffValue = $priceDiffCell->getValue();

        // Check if PriceDiff column contains "Not Found"
        if ($priceDiffValue === 'Not Found' || strpos($priceDiffValue, 'Not Found') !== false) {
            $notFoundBarcodeRows[] = $i;
        }
    }
}

// Handle CHP search
$chpResults = null;
$chpError = null;
$searchBarcode = '';
$searchCity = $defaultCity;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chp_search'])) {
    $searchBarcode = trim($_POST['barcode'] ?? '');
    $searchCity = trim($_POST['city'] ?? $defaultCity);

    if (empty($searchBarcode) || empty($searchCity)) {
        $chpError = 'נא למלא ברקוד ועיר';
    } else {
        $client = new CHPHeadlessClient();

        // Capture output to suppress debug messages
        ob_start();
        $chpResults = $client->searchPrices($searchBarcode, $searchCity);
        ob_end_clean(); // Discard the output

        if (!$chpResults || empty($chpResults['prices'])) {
            $chpError = 'לא נמצאו תוצאות עבור הברקוד והעיר שהוזנו';
        }
    }
}

// Handle re-calculation of recommended margins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalculate'])) {
    $updatedPrices = $_POST['rec_price'] ?? [];
    $originalPrices = $_POST['original_rec_price'] ?? [];

    // Reload spreadsheet to make changes
    $spreadsheet = IOFactory::load($pcFilePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Find column indices
    $headers = $sheet->rangeToArray('A1:P1')[0];
    $colActualUnitPrice = array_search('ActualUnitPrice', $headers); // Column E (index 4)
    $colSalesPrice = array_search('SalesPrice', $headers); // Column I (index 8)
    $colRecPrice = array_search('Rec Price', $headers); // Column N (index 13)
    $colRecommend = array_search('Recommend', $headers); // Column O (index 14)
    $colRecMrgn = array_search('Rec Mrgn', $headers); // Column P (index 15)

    $modifiedCount = 0;

    // Only process rows where the value actually changed
    foreach ($updatedPrices as $rowIndex => $newRecPrice) {
        $rowNum = (int)$rowIndex; // This is the spreadsheet row number (1-based)

        if ($rowNum <= 1) continue; // Skip header row

        $originalPrice = isset($originalPrices[$rowIndex]) ? (float)$originalPrices[$rowIndex] : null;
        $newRecPrice = !empty($newRecPrice) ? (float)$newRecPrice : null;

        // Skip if value didn't change or is empty
        if ($newRecPrice === null || $originalPrice === null || $newRecPrice == $originalPrice) {
            continue;
        }

        // This row was modified - recalculate
        $modifiedCount++;

        // Get ActualUnitPrice (column E) and SalesPrice (column I)
        $actualUnitPrice = (float)$sheet->getCellByColumnAndRow($colActualUnitPrice + 1, $rowNum)->getValue();
        $salesPrice = (float)$sheet->getCellByColumnAndRow($colSalesPrice + 1, $rowNum)->getValue();

        // Update Rec Price (column N)
        $sheet->setCellValueByColumnAndRow($colRecPrice + 1, $rowNum, $newRecPrice);

        // Calculate new Rec Mrgn (column P): ((Y/1.18)-X)/(Y/1.18)
        // where Y = Rec Price, X = ActualUnitPrice
        $priceBeforeVAT = $newRecPrice / 1.18;
        $newRecMrgn = (($priceBeforeVAT - $actualUnitPrice) / $priceBeforeVAT) * 100;
        $sheet->setCellValueByColumnAndRow($colRecMrgn + 1, $rowNum, number_format($newRecMrgn, 2, '.', '') . '%');

        // Set background color to light orange for modified Rec Mrgn cells
        $recMrgnCell = $sheet->getCellByColumnAndRow($colRecMrgn + 1, $rowNum);
        $recMrgnCell->getStyle()->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFD700'); // Light orange

        // Update Recommend (column O) based on comparison with SalesPrice
        $recommend = '';
        if ($salesPrice == $newRecPrice) {
            $recommend = 'NO';
        } elseif ($salesPrice > $newRecPrice) {
            $recommend = 'YES. decrease';
        } else { // $salesPrice < $newRecPrice
            $recommend = 'YES. increase';
        }
        $sheet->setCellValueByColumnAndRow($colRecommend + 1, $rowNum, $recommend);
    }

    // Save the updated spreadsheet only if changes were made
    if ($modifiedCount > 0) {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($pcFilePath);

        // Reload the data for display
        $pcData = $sheet->toArray();

        // Reload modified rows tracking
        $modifiedRows = [];
        if ($colRecMrgnIdx !== false) {
            for ($i = 2; $i <= count($pcData); $i++) {
                $cell = $sheet->getCellByColumnAndRow($colRecMrgnIdx + 1, $i);
                $fillColor = $cell->getStyle()->getFill()->getStartColor()->getARGB();
                if ($fillColor === 'FFFFD700') {
                    $modifiedRows[] = $i;
                }
            }
        }

        // Set success message
        $recalcSuccess = $modifiedCount;
    } else {
        $recalcSuccess = 0; // No changes made
    }
}

// Handle finalization (continue to New Product Process)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize'])) {
    // Extract CL filename from session
    $clFileName = $_SESSION['clFileName'] ?? null;

    if (!$clFileName) {
        die('Error: CL file name not found in session');
    }

    // Redirect with flag to trigger NP generation via JavaScript
    $_SESSION['continue_to_np'] = true;
    header('Location: verify_price_changes.php?pcFile=' . urlencode($pcFileName) . '&shop=' . urlencode($shopName) . '&continueToNP=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>אימות שינויי מחירים - Commercial Layer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            direction: ltr; /* Force LTR for header */
        }

        .header h1 {
            font-size: 1.8em;
            margin: 0;
            direction: ltr;
            text-align: left;
        }

        .header .file-info {
            font-size: 0.9em;
            opacity: 0.9;
            direction: ltr;
            text-align: left;
        }

        .finalize-button {
            padding: 10px 25px;
            font-size: 1.1em;
            font-weight: bold;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .finalize-button:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .split-container {
            display: flex;
            height: calc(100vh - 70px);
            gap: 10px;
            padding: 10px;
        }

        .left-panel {
            width: 500px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .right-panel {
            flex: 1;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-size: 1.3em;
            font-weight: bold;
        }

        .panel-content {
            flex: 1;
            overflow: auto;
            padding: 15px;
        }

        /* PC Table Styles */
        .pc-table-container {
            overflow: auto;
            flex: 1;
            direction: ltr; /* Force LTR for spreadsheet */
        }

        .pc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85em;
            direction: ltr; /* Force LTR for spreadsheet */
        }

        .pc-table thead {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }

        .pc-table th {
            padding: 10px 8px;
            text-align: left; /* LTR: align left */
            font-weight: bold;
            border-bottom: 2px solid #667eea;
            background: #f8f9fa;
            white-space: nowrap;
        }

        .pc-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }

        .pc-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .pc-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .pc-table td {
            padding: 8px;
            text-align: left; /* LTR: align left */
            border-left: 1px solid #e0e0e0; /* LTR: border on left instead of right */
        }

        .pc-table td:first-child {
            border-left: none;
        }

        .barcode-cell {
            cursor: pointer;
            color: #667eea;
            font-weight: bold;
            text-decoration: underline;
            position: relative;
        }

        .barcode-cell:hover {
            color: #764ba2;
            background-color: #e9ecef;
        }

        .barcode-cell .tooltip {
            position: absolute;
            bottom: 100%;
            right: 0;
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
            z-index: 1000;
        }

        .barcode-cell:hover .tooltip {
            opacity: 1;
        }

        /* Bold border for barcodes with "Not Found" in PriceDiff */
        .not-found-barcode {
            border: 3px solid #000 !important;
        }

        .copied-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            animation: slideIn 0.3s, slideOut 0.3s 2.7s;
            opacity: 0;
        }

        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }

        .copied-notification.show {
            opacity: 1;
        }

        /* CHP Search Form Styles */
        .chp-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 1em;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .form-group input {
            padding: 12px;
            font-size: 1em;
            border: 2px solid #ddd;
            border-radius: 6px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group input:read-only {
            background: #f8f9fa;
        }

        .search-button {
            padding: 12px 20px;
            font-size: 1.1em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .search-button:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fee;
            border: 2px solid #fcc;
            border-radius: 6px;
            padding: 12px;
            color: #c33;
            text-align: center;
            margin-bottom: 15px;
        }

        /* CHP Results Styles */
        .chp-results {
            margin-top: 20px;
        }

        .chp-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-box .value {
            font-size: 1.4em;
            font-weight: bold;
            display: block;
        }

        .stat-box .label {
            font-size: 0.85em;
            opacity: 0.9;
        }

        .chp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85em;
        }

        .chp-table thead {
            background: #f8f9fa;
        }

        .chp-table th {
            padding: 8px;
            text-align: right;
            font-weight: bold;
            border-bottom: 2px solid #667eea;
        }

        .chp-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }

        .chp-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .chp-table td {
            padding: 8px;
            text-align: right;
        }

        .best-price-row {
            background-color: #d4edda !important;
            border-right: 4px solid #28a745;
        }

        .price-cell {
            font-weight: bold;
            color: #27ae60;
            font-size: 1.1em;
        }

        .recommendation-yes {
            color: #28a745;
            font-weight: bold;
        }

        .recommendation-no {
            color: #dc3545;
        }

        /* Editable cell styles */
        .editable-cell {
            background-color: #fffacd !important; /* Light yellow to indicate editable */
        }

        .editable-cell input {
            width: 100%;
            padding: 4px 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
            text-align: left;
        }

        .editable-cell input:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
        }

        /* Modified cell (light orange background) */
        .modified-cell {
            background-color: #FFD700 !important; /* Light orange */
        }

        /* Recalculate button */
        .recalculate-button {
            padding: 10px 25px;
            font-size: 1.1em;
            font-weight: bold;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 15px;
        }

        .recalculate-button:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
        }

        /* Success message */
        .success-message {
            background: #d4edda;
            border: 2px solid #c3e6cb;
            border-radius: 8px;
            padding: 12px 20px;
            color: #155724;
            text-align: center;
            margin: 10px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🔍 Verify Price Changes</h1>
            <div class="file-info">File: <?= htmlspecialchars($pcFileName) ?> | Shop: <?= htmlspecialchars($shopName) ?></div>
        </div>
        <div>
            <button type="button" class="recalculate-button" id="recalculateBtn">
                🔄 Re-calculate Recommended Margin
            </button>
            <form method="POST" style="display: inline;">
                <button type="submit" name="finalize" class="finalize-button" onclick="return confirm('Continue to New Product process?')">
                    ➡️ Continue to New Product Process
                </button>
            </form>
        </div>
    </div>

    <div id="recalcMessage"></div>

    <div class="split-container">
        <!-- LEFT PANEL: CHP Search -->
        <div class="left-panel">
            <div class="panel-header">
                🔎 חיפוש מחירים בשוק
            </div>
            <div class="panel-content">
                <form method="POST" class="chp-form" id="chpSearchForm">
                    <div class="form-group">
                        <label for="city">עיר:</label>
                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="<?= htmlspecialchars($searchCity) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="barcode">ברקוד מוצר:</label>
                        <input
                            type="text"
                            id="barcode"
                            name="barcode"
                            placeholder="לחץ על ברקוד מהטבלה להעתקה"
                            value="<?= htmlspecialchars($searchBarcode) ?>"
                            required
                        >
                    </div>

                    <button type="submit" name="chp_search" class="search-button">
                        🔎 חפש במאגר המחירים
                    </button>
                </form>

                <div id="chpResultsContainer">

                <?php if ($chpError): ?>
                    <div class="error-message">
                        ⚠️ <?= htmlspecialchars($chpError) ?>
                    </div>
                <?php endif; ?>

                <?php if ($chpResults && !empty($chpResults['prices'])): ?>
                    <div class="chp-results">
                        <?php
                        // Calculate statistics
                        $prices = array_filter(
                            array_map(function($item) {
                                return is_numeric($item['price']) ? (float)$item['price'] : null;
                            }, $chpResults['prices'])
                        );

                        $minPrice = !empty($prices) ? min($prices) : 0;
                        $maxPrice = !empty($prices) ? max($prices) : 0;
                        $avgPrice = !empty($prices) ? array_sum($prices) / count($prices) : 0;
                        ?>

                        <div class="chp-stats">
                            <div class="stat-box">
                                <span class="value"><?= number_format($minPrice, 2) ?> ₪</span>
                                <span class="label">מחיר מינימום</span>
                            </div>
                            <div class="stat-box">
                                <span class="value"><?= number_format($avgPrice, 2) ?> ₪</span>
                                <span class="label">מחיר ממוצע</span>
                            </div>
                            <div class="stat-box">
                                <span class="value"><?= number_format($maxPrice, 2) ?> ₪</span>
                                <span class="label">מחיר מקסימום</span>
                            </div>
                            <div class="stat-box">
                                <span class="value"><?= count($chpResults['prices']) ?></span>
                                <span class="label">חנויות</span>
                            </div>
                        </div>

                        <?php if (!empty($chpResults['productName'])): ?>
                            <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                                <strong>שם המוצר:</strong> <?= htmlspecialchars($chpResults['productName']) ?>
                            </div>
                        <?php endif; ?>

                        <table class="chp-table">
                            <thead>
                                <tr>
                                    <th>רשת</th>
                                    <th>חנות</th>
                                    <th>מחיר</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chpResults['prices'] as $item): ?>
                                    <?php
                                    $isBestPrice = is_numeric($item['price']) && (float)$item['price'] == $minPrice;
                                    ?>
                                    <tr class="<?= $isBestPrice ? 'best-price-row' : '' ?>">
                                        <td><?= htmlspecialchars($item['chain'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($item['store'] ?? '') ?></td>
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
            </div>
        </div>

        <!-- RIGHT PANEL: PC File Data -->
        <div class="right-panel">
            <div class="panel-header">
                📊 נתוני שינוי מחירים
            </div>

            <!-- Full PC File Table (All columns A-P, all rows) -->
            <div class="pc-table-container">
                <form method="POST" id="recalcForm">
                    <input type="hidden" name="recalculate" value="1">
                    <table class="pc-table">
                        <thead>
                            <tr>
                                <?php foreach ($pcData[0] as $header): ?>
                                    <th><?= htmlspecialchars($header) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i < count($pcData); $i++): ?>
                                <?php $row = $pcData[$i]; ?>
                                <tr>
                                    <?php foreach ($row as $colIdx => $cell): ?>
                                        <?php if ($pcData[0][$colIdx] === 'Barcode'): ?>
                                            <?php
                                            // Check if this barcode row has "Not Found" (row number is $i + 1 in spreadsheet terms)
                                            $hasNotFound = in_array($i + 1, $notFoundBarcodeRows);
                                            ?>
                                            <td class="barcode-cell <?= $hasNotFound ? 'not-found-barcode' : '' ?>" onclick="copyBarcode('<?= htmlspecialchars($cell) ?>')">
                                                <?= htmlspecialchars($cell) ?>
                                                <span class="tooltip">לחץ להעתקה</span>
                                            </td>
                                        <?php elseif ($pcData[0][$colIdx] === 'Rec Price'): ?>
                                            <td class="editable-cell">
                                                <!-- Hidden field to store original value -->
                                                <input
                                                    type="hidden"
                                                    name="original_rec_price[<?= $i + 1 ?>]"
                                                    value="<?= htmlspecialchars($cell) ?>"
                                                >
                                                <!-- Editable field for new value -->
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="rec_price[<?= $i + 1 ?>]"
                                                    value="<?= htmlspecialchars($cell) ?>"
                                                    placeholder="<?= htmlspecialchars($cell) ?>"
                                                >
                                            </td>
                                        <?php elseif ($pcData[0][$colIdx] === 'Rec Mrgn'): ?>
                                            <?php
                                            // Check if this row was modified (row number is $i + 1 in spreadsheet terms)
                                            $isModified = in_array($i + 1, $modifiedRows);
                                            ?>
                                            <td class="<?= $isModified ? 'modified-cell' : '' ?>">
                                                <?= htmlspecialchars($cell) ?>
                                            </td>
                                        <?php elseif ($pcData[0][$colIdx] === 'Recommend'): ?>
                                            <td class="<?= strpos($cell, 'YES') === 0 ? 'recommendation-yes' : 'recommendation-no' ?>">
                                                <?= htmlspecialchars($cell) ?>
                                            </td>
                                        <?php else: ?>
                                            <td><?= htmlspecialchars($cell) ?></td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>

    <!-- Copied Notification -->
    <div id="copiedNotification" class="copied-notification">
        ✓ ברקוד הועתק בהצלחה!
    </div>

    <script>
        function copyBarcode(barcode) {
            // Copy to clipboard
            navigator.clipboard.writeText(barcode).then(function() {
                // Update the barcode field
                document.getElementById('barcode').value = barcode;

                // Show notification
                const notification = document.getElementById('copiedNotification');
                notification.classList.add('show');

                // Hide after 3 seconds
                setTimeout(function() {
                    notification.classList.remove('show');
                }, 3000);
            }).catch(function(err) {
                alert('שגיאה בהעתקה: ' + err);
            });
        }

        // Handle CHP search form submission via AJAX
        document.getElementById('chpSearchForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent page reload

            const formData = new FormData(this);
            formData.append('chp_search', '1');

            // Show loading indicator
            const resultsContainer = document.getElementById('chpResultsContainer');
            resultsContainer.innerHTML = '<div style="text-align: center; padding: 20px;">🔄 מחפש...</div>';

            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Parse the response HTML to extract just the results section
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newResults = doc.getElementById('chpResultsContainer');

                if (newResults) {
                    resultsContainer.innerHTML = newResults.innerHTML;
                } else {
                    resultsContainer.innerHTML = '<div class="error-message">⚠️ שגיאה בטעינת התוצאות</div>';
                }
            })
            .catch(error => {
                resultsContainer.innerHTML = '<div class="error-message">⚠️ שגיאה בחיפוש: ' + error.message + '</div>';
            });
        });

        // Handle recalculate button click via AJAX
        document.getElementById('recalculateBtn').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('recalcForm'));
            formData.append('recalculate', '1');

            // Show loading message
            const messageDiv = document.getElementById('recalcMessage');
            messageDiv.innerHTML = '<div style="background: #e3f2fd; border: 2px solid #2196F3; padding: 15px; margin: 15px 30px; border-radius: 8px; text-align: center;">🔄 Recalculating...</div>';

            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Parse the response to extract the updated table
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTable = doc.querySelector('#recalcForm table');
                const oldTable = document.querySelector('#recalcForm table');

                if (newTable && oldTable) {
                    // Replace the table with updated data
                    oldTable.innerHTML = newTable.innerHTML;

                    // Show success message
                    messageDiv.innerHTML = '<div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; margin: 15px 30px; border-radius: 8px; text-align: center; color: #155724;">✓ Recommended margins recalculated successfully!</div>';

                    // Hide message after 3 seconds
                    setTimeout(() => {
                        messageDiv.innerHTML = '';
                    }, 3000);
                } else {
                    messageDiv.innerHTML = '<div style="background: #f8d7da; border: 2px solid #dc3545; padding: 15px; margin: 15px 30px; border-radius: 8px; text-align: center; color: #721c24;">⚠️ Error updating table</div>';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<div style="background: #f8d7da; border: 2px solid #dc3545; padding: 15px; margin: 15px 30px; border-radius: 8px; text-align: center; color: #721c24;">⚠️ Error: ' + error.message + '</div>';
            });
        });

        // Check if we need to continue to NP process
        <?php if (isset($_GET['continueToNP']) && $_GET['continueToNP'] == '1'): ?>
        window.addEventListener('DOMContentLoaded', function() {
            // Get CL filename from session
            const clFileName = '<?= $_SESSION['clFileName'] ?? '' ?>';
            const shopName = '<?= $shopName ?>';

            if (!clFileName) {
                alert('Error: CL file name not found');
                return;
            }

            // Call generate_np_file.php
            fetch('generate_np_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    clFileName: clFileName,
                    shopName: shopName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.hasNewProducts) {
                        alert('✅ New Products file created!\n\n' +
                              'Products found: ' + data.newProductCount + '\n' +
                              'Supplier: ' + data.supplierHebrewName + '\n\n' +
                              'Redirecting to New Product verification...');

                        // Redirect to NP verification page
                        window.location.href = data.redirectUrl;
                    } else {
                        // No new products - go to completion page
                        alert('ℹ️ ' + data.message);
                        window.location.href = 'price_change_complete.php?pcFile=' + encodeURIComponent('<?= $pcFileName ?>') + '&shop=' + encodeURIComponent('<?= $shopName ?>') + '&cl=' + encodeURIComponent(clFileName) + '&noNP=true';
                    }
                } else {
                    alert('❌ Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('❌ Error generating NP file: ' + error);
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
