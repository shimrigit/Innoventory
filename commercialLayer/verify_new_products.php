<?php
/**
 * New Products Verification Interface
 * Split-screen view: NP file data (right) + CHP price search (left)
 */

session_start();

// Load required files
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/chp_headless_client.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Get parameters from session or query
$npFileName = $_GET['npFile'] ?? $_SESSION['npFileName'] ?? null;
$shopName = $_GET['shop'] ?? $_SESSION['shopName'] ?? 'CountryMZ';

if (!$npFileName) {
    die('Error: No NP file specified');
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

// Load departments configuration for dropdown
$departmentsConfigPath = __DIR__ . '/../configDir/' . $shopName . '_Departments.json';
$departments = [];
if (file_exists($departmentsConfigPath)) {
    $departmentsConfig = json_decode(file_get_contents($departmentsConfigPath), true);
    foreach ($departmentsConfig as $dept) {
        if (isset($dept['DepartmentName'])) {
            $departments[] = $dept['DepartmentName'];
        }
    }
}

// Load NP file data
$npFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'commercial_invoice_files' . DIRECTORY_SEPARATOR . $npFileName;
if (!file_exists($npFilePath)) {
    die('Error: NP file not found: ' . $npFilePath);
}

$spreadsheet = IOFactory::load($npFilePath);
$sheet = $spreadsheet->getActiveSheet();
$npData = $sheet->toArray();

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

// Handle save updated NP data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_np_data'])) {
    $barcodes = $_POST['barcode'] ?? [];
    $itemERPNames = $_POST['item_erp_name'] ?? [];
    $departmentNames = $_POST['department_name'] ?? [];
    $salePrices = $_POST['sale_price'] ?? [];

    // Reload spreadsheet to make changes
    $spreadsheet = IOFactory::load($npFilePath);
    $sheet = $spreadsheet->getActiveSheet();

    // Load department codes for mapping DepartmentName -> DepartmentNumber
    $shopDepartmentsPath = __DIR__ . '/../configDir/' . $shopName . '_Departments.json';
    $departmentMap = [];

    if (file_exists($shopDepartmentsPath)) {
        $shopDepartments = json_decode(file_get_contents($shopDepartmentsPath), true);
        if ($shopDepartments) {
            foreach ($shopDepartments as $dept) {
                $departmentMap[$dept['DepartmentName']] = $dept['DepartmentNumber'];
            }
        }
    }

    // Update each row
    foreach ($itemERPNames as $rowIndex => $value) {
        $rowNum = (int)$rowIndex;
        if ($rowNum <= 1) continue; // Skip header

        // Update Barcode (column D) if changed
        if (isset($barcodes[$rowIndex])) {
            $sheet->setCellValue('D' . $rowNum, $barcodes[$rowIndex] ?? '');
        }

        // Update ItemERPName (column F)
        $sheet->setCellValue('F' . $rowNum, $value ?? '');

        // Update DepartmentName (column I)
        $departmentName = $departmentNames[$rowIndex] ?? '';
        $sheet->setCellValue('I' . $rowNum, $departmentName);

        // Update DepartmentCode (column O) based on DepartmentName
        $departmentCode = isset($departmentMap[$departmentName]) ? $departmentMap[$departmentName] : '';
        $sheet->setCellValue('O' . $rowNum, $departmentCode);

        // Update SalePrice (column K)
        $sheet->setCellValue('K' . $rowNum, $salePrices[$rowIndex] ?? '');
    }

    // Save the updated spreadsheet
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($npFilePath);

    // Reload the data for display
    $npData = $sheet->toArray();

    $saveSuccess = true;
}

// Handle finalization (save NP file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize'])) {
    // The NP file is already saved
    $_SESSION['np_complete'] = true;
    header('Location: new_products_complete.php?npFile=' . urlencode($npFileName));
    exit;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>אימות מוצרים חדשים - Commercial Layer</title>

    <!-- jQuery (required for Select2) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .header h1 {
            font-size: 1.8em;
            text-align: left;
            direction: ltr;
        }

        .file-info {
            font-size: 0.9em;
            opacity: 0.9;
            margin-top: 5px;
        }

        .finalize-button {
            padding: 12px 25px;
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

        .calculate-button {
            padding: 12px 25px;
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

        .calculate-button:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
        }

        .split-container {
            display: flex;
            height: calc(100vh - 80px);
        }

        /* CHP Panel (LEFT) */
        .left-panel {
            width: 500px;
            background: white;
            border-left: 3px solid #667eea;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        /* NP File Panel (RIGHT) */
        .right-panel {
            flex: 1;
            background: white;
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
            text-align: center;
        }

        .np-table-container {
            overflow: auto;
            flex: 1;
            direction: ltr; /* Force LTR for spreadsheet */
        }

        .np-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1.1em;
            direction: ltr; /* Force LTR for spreadsheet */
        }

        .np-table thead {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 10;
        }

        .np-table th {
            padding: 12px 10px;
            text-align: left; /* LTR: align left */
            font-weight: bold;
            border-bottom: 2px solid #667eea;
            background: #f8f9fa;
            white-space: nowrap;
        }

        .np-table tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }

        .np-table tbody tr:hover {
            background-color: #f5f5f5;
        }

        .np-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .np-table td {
            padding: 10px;
            text-align: left; /* LTR: align left */
            border-left: 1px solid #e0e0e0; /* LTR: border on left instead of right */
        }

        .np-table td:first-child {
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

        .search-button {
            padding: 12px 20px;
            font-size: 1.1em;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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
            font-size: 1.8em;
            font-weight: bold;
            display: block;
        }

        .stat-box .label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .chp-table-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .chp-table {
            width: 100%;
            border-collapse: collapse;
        }

        .chp-table thead {
            position: sticky;
            top: 0;
            background: #667eea;
            color: white;
            z-index: 5;
        }

        .chp-table th {
            padding: 12px 10px;
            text-align: center;
            font-weight: bold;
        }

        .chp-table td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #e0e0e0;
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

        /* Editable cells */
        .editable-cell {
            background-color: #fffacd !important; /* Light yellow */
        }

        .editable-cell input,
        .editable-cell select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            text-align: left;
        }

        .editable-cell input:focus,
        .editable-cell select:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
        }

        /* Barcode editable cell - both clickable and editable */
        .barcode-editable-cell input {
            cursor: pointer;
            color: #667eea;
            font-weight: bold;
        }

        .barcode-editable-cell input:hover {
            background-color: #e9ecef;
            border-color: #667eea;
        }

        /* Placeholder cells with orange border */
        .placeholder-cell {
            color: #ff9800;
            font-weight: bold;
            border: 2px solid #ff9800 !important;
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

        /* Select2 styling overrides */
        .select2-container {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--single {
            border: 1px solid #ddd;
            border-radius: 4px;
            height: auto;
            padding: 6px 8px;
            font-size: 1em;
            text-align: left;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding: 0;
            line-height: normal;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100%;
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #667eea;
        }

        .select2-dropdown {
            border: 1px solid #667eea;
            border-radius: 4px;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea;
        }

        .select2-search--dropdown .select2-search__field {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 6px;
            font-size: 0.9em;
        }

        .select2-search--dropdown .select2-search__field:focus {
            border-color: #667eea;
            outline: none;
        }

        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 1.2em;
            color: #333;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <button type="button" class="calculate-button" onclick="calculateMargins()">
                🧮 Calculate Margins
            </button>
            <form method="POST" style="display: inline;">
                <button type="submit" name="finalize" class="finalize-button" onclick="return confirm('Save New Products file?')">
                    💾 Save New Products File
                </button>
            </form>
        </div>
        <div>
            <h1>🆕 Verify New Products</h1>
            <div class="file-info">File: <?= htmlspecialchars($npFileName) ?> | Shop: <?= htmlspecialchars($shopName) ?></div>
        </div>
    </div>

    <?php if (isset($saveSuccess)): ?>
        <div class="success-message">
            ✓ New Products data saved successfully!
        </div>
    <?php endif; ?>

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

        <!-- RIGHT PANEL: NP File Data -->
        <div class="right-panel">
            <div class="panel-header">
                📋 נתוני מוצרים חדשים
            </div>

            <!-- Full NP File Table (All columns, all rows) -->
            <div class="np-table-container">
                <form method="POST" id="npForm">
                    <input type="hidden" name="save_np_data" value="1">
                    <table class="np-table">
                        <thead>
                            <tr>
                                <?php foreach ($npData[0] as $colIdx => $header): ?>
                                    <?php
                                    // Skip columns A and B in display (except we show the header)
                                    if ($colIdx >= 2) { // Only show from column C onwards (index 2+)
                                    ?>
                                        <th><?= htmlspecialchars($header) ?></th>
                                    <?php } ?>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i < count($npData); $i++): ?>
                                <?php
                                $row = $npData[$i];

                                // Check if this row has product data (check if Barcode column has value)
                                $barcodeColIdx = array_search('Barcode', $npData[0]);
                                $hasProductData = !empty($row[$barcodeColIdx] ?? '');

                                // Skip empty rows (rows without barcode)
                                if (!$hasProductData) {
                                    continue;
                                }
                                ?>
                                <tr>
                                    <?php foreach ($row as $colIdx => $cell): ?>
                                        <?php
                                        // Skip columns A and B (indices 0 and 1) for data rows
                                        if ($colIdx < 2) {
                                            continue; // Don't display columns A and B
                                        }

                                        $headerName = $npData[0][$colIdx] ?? '';
                                        $rowNum = $i + 1; // Spreadsheet row number (1-based)
                                        ?>

                                        <?php if ($headerName === 'Barcode'): ?>
                                            <td class="editable-cell barcode-editable-cell">
                                                <input
                                                    type="text"
                                                    name="barcode[<?= $rowNum ?>]"
                                                    value="<?= htmlspecialchars($cell) ?>"
                                                    onclick="copyBarcodeFromInput(this)"
                                                    placeholder="Enter barcode"
                                                    title="Click to copy to CHP search"
                                                >
                                            </td>

                                        <?php elseif ($headerName === 'ItemERPName'): ?>
                                            <td class="editable-cell">
                                                <input
                                                    type="text"
                                                    name="item_erp_name[<?= $rowNum ?>]"
                                                    value="<?= htmlspecialchars($cell) ?>"
                                                    placeholder="Enter ERP name"
                                                >
                                            </td>

                                        <?php elseif ($headerName === 'DepartmentName'): ?>
                                            <td class="editable-cell">
                                                <select name="department_name[<?= $rowNum ?>]" class="department-select">
                                                    <option value="">-- Select Department --</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <option value="<?= htmlspecialchars($dept) ?>" <?= $cell == $dept ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($dept) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>

                                        <?php elseif ($headerName === 'DepartmentMargin' || $headerName === 'ActualMargin'): ?>
                                            <td class="placeholder-cell">
                                                <?= htmlspecialchars($cell) ?>
                                            </td>

                                        <?php elseif ($headerName === 'SalePrice'): ?>
                                            <td class="editable-cell">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    name="sale_price[<?= $rowNum ?>]"
                                                    value="<?= htmlspecialchars($cell) ?>"
                                                    placeholder="Enter sale price"
                                                >
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

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">מחשב שוליים...</div>
        </div>
    </div>

    <script>
        // Initialize Select2 for all department dropdowns
        $(document).ready(function() {
            $('.department-select').select2({
                placeholder: '-- Select Department --',
                allowClear: true,
                width: '100%',
                dir: 'rtl'
            });
        });

        function calculateMargins() {
            // First, save the current form data
            const formData = new FormData(document.getElementById('npForm'));

            // Show loading overlay
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('show');

            // Submit the form to save data first
            fetch('<?= $_SERVER['PHP_SELF'] ?>?npFile=<?= urlencode($npFileName) ?>&shop=<?= urlencode($shopName) ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // After saving, call the margin calculation endpoint
                return fetch('calculate_margins.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        npFileName: '<?= $npFileName ?>',
                        shopName: '<?= $shopName ?>'
                    })
                });
            })
            .then(response => response.json())
            .then(data => {
                loadingOverlay.classList.remove('show');

                if (data.success) {
                    // Reload only the NP table without refreshing the whole page
                    return fetch(window.location.href);
                } else {
                    alert('❌ Error: ' + data.error);
                    throw new Error(data.error);
                }
            })
            .then(response => response.text())
            .then(html => {
                // Parse the response to extract the updated table
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTable = doc.querySelector('#npForm table');
                const oldTable = document.querySelector('#npForm table');

                if (newTable && oldTable) {
                    // Replace the table with updated data
                    oldTable.innerHTML = newTable.innerHTML;

                    // Re-initialize Select2 on the new department dropdowns
                    $('.department-select').select2({
                        placeholder: 'Select department',
                        allowClear: true,
                        width: '100%',
                        dir: 'rtl'
                    });

                    alert('✅ Margins calculated successfully!');
                } else {
                    alert('⚠️ Error updating table');
                }
            })
            .catch(error => {
                loadingOverlay.classList.remove('show');
                alert('❌ Error calculating margins: ' + error);
                console.error('Error:', error);
            });
        }

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

        function copyBarcodeFromInput(inputElement) {
            // Get the barcode value from the input field
            const barcode = inputElement.value;

            if (!barcode) {
                return; // Don't copy if empty
            }

            // Copy to clipboard
            navigator.clipboard.writeText(barcode).then(function() {
                // Update the CHP barcode search field
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
    </script>
</body>
</html>
