<?php
/**
 * Suppliers Configuration Setup Tool
 * Manages supplier data in suppliers.json
 */

$suppliersFile = dirname(__DIR__) . '/suppliers.json';
$errors = [];
$success = '';
$mode = ''; // 'edit' or 'new'
$selectedSupplier = null;

// Load suppliers
function loadSuppliers($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?? [];
}

// Save suppliers
function saveSuppliers($file, $suppliers) {
    $json = json_encode($suppliers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json) !== false;
}

// Validate supplier data
function validateSupplier($data, &$errors) {
    $errors = [];

    // Validate supplierName (mandatory, English, no spaces)
    if (empty($data['supplierName'])) {
        $errors['supplierName'] = 'שדה חובה';
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $data['supplierName'])) {
        $errors['supplierName'] = 'חייב להיות באנגלית ללא רווחים';
    }

    // Validate hebrewName (mandatory)
    if (empty($data['hebrewName'])) {
        $errors['hebrewName'] = 'שדה חובה';
    }

    // Validate OCRsanityMethod (mandatory)
    if (empty($data['OCRsanityMethod'])) {
        $errors['OCRsanityMethod'] = 'שדה חובה';
    } elseif (!in_array($data['OCRsanityMethod'], ['Simple', 'LineTotal', 'Discount1', 'Discount2'])) {
        $errors['OCRsanityMethod'] = 'ערך לא חוקי';
    }

    // Validate jsonToOcrSanity if enabled
    if (!empty($data['hasJsonMapping'])) {
        // Mandatory fields
        if (empty($data['Barcode']) || !is_numeric($data['Barcode']) || intval($data['Barcode']) != $data['Barcode']) {
            $errors['Barcode'] = 'חייב להיות מספר שלם';
        }
        if (empty($data['ItemName']) || !is_numeric($data['ItemName']) || intval($data['ItemName']) != $data['ItemName']) {
            $errors['ItemName'] = 'חייב להיות מספר שלם';
        }
        if (empty($data['Qty']) || !is_numeric($data['Qty']) || intval($data['Qty']) != $data['Qty']) {
            $errors['Qty'] = 'חייב להיות מספר שלם';
        }

        // Optional fields - validate only if provided
        if (!empty($data['UnitPrice']) && (!is_numeric($data['UnitPrice']) || intval($data['UnitPrice']) != $data['UnitPrice'])) {
            $errors['UnitPrice'] = 'חייב להיות מספר שלם';
        }
        if (!empty($data['LineTotal']) && (!is_numeric($data['LineTotal']) || intval($data['LineTotal']) != $data['LineTotal'])) {
            $errors['LineTotal'] = 'חייב להיות מספר שלם';
        }
        if (!empty($data['Discount1']) && (!is_numeric($data['Discount1']) || intval($data['Discount1']) != $data['Discount1'])) {
            $errors['Discount1'] = 'חייב להיות מספר שלם';
        }
        if (!empty($data['Discount2']) && (!is_numeric($data['Discount2']) || intval($data['Discount2']) != $data['Discount2'])) {
            $errors['Discount2'] = 'חייב להיות מספר שלם';
        }
    }

    return empty($errors);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'edit' && !empty($_POST['supplier'])) {
            $mode = 'edit';
            $suppliers = loadSuppliers($suppliersFile);
            foreach ($suppliers as $supplier) {
                if ($supplier['hebrewName'] === $_POST['supplier']) {
                    $selectedSupplier = $supplier;
                    break;
                }
            }
        } elseif ($_POST['action'] === 'new') {
            $mode = 'new';
            $selectedSupplier = [
                'supplierName' => '',
                'OCRsanityMethod' => 'LineTotal',
                'hebrewName' => ''
            ];
        } elseif ($_POST['action'] === 'save') {
            $mode = $_POST['mode'];

            // Build supplier data from form
            $supplierData = [
                'supplierName' => trim($_POST['supplierName'] ?? ''),
                'OCRsanityMethod' => $_POST['OCRsanityMethod'] ?? '',
                'hebrewName' => trim($_POST['hebrewName'] ?? ''),
                'hasJsonMapping' => !empty($_POST['hasJsonMapping'])
            ];

            // Add jsonToOcrSanity fields if checkbox is checked
            if (!empty($_POST['hasJsonMapping'])) {
                $supplierData['Barcode'] = trim($_POST['Barcode'] ?? '');
                $supplierData['ItemName'] = trim($_POST['ItemName'] ?? '');
                $supplierData['Qty'] = trim($_POST['Qty'] ?? '');
                $supplierData['UnitPrice'] = trim($_POST['UnitPrice'] ?? '');
                $supplierData['LineTotal'] = trim($_POST['LineTotal'] ?? '');
                $supplierData['Discount1'] = trim($_POST['Discount1'] ?? '');
                $supplierData['Discount2'] = trim($_POST['Discount2'] ?? '');
            }

            // Validate
            if (validateSupplier($supplierData, $errors)) {
                $suppliers = loadSuppliers($suppliersFile);

                // Build final supplier object
                $newSupplier = [
                    'supplierName' => $supplierData['supplierName'],
                    'OCRsanityMethod' => $supplierData['OCRsanityMethod'],
                    'hebrewName' => $supplierData['hebrewName']
                ];

                // Add jsonToOcrSanity if enabled
                if ($supplierData['hasJsonMapping']) {
                    $jsonMapping = [
                        'Barcode' => intval($supplierData['Barcode']),
                        'ItemName' => intval($supplierData['ItemName']),
                        'Qty' => intval($supplierData['Qty'])
                    ];

                    // Add optional fields only if they have values
                    if (!empty($supplierData['UnitPrice']) && intval($supplierData['UnitPrice']) > 0) {
                        $jsonMapping['UnitPrice'] = intval($supplierData['UnitPrice']);
                    }
                    if (!empty($supplierData['LineTotal']) && intval($supplierData['LineTotal']) > 0) {
                        $jsonMapping['LineTotal'] = intval($supplierData['LineTotal']);
                    }
                    if (!empty($supplierData['Discount1']) && intval($supplierData['Discount1']) > 0) {
                        $jsonMapping['Discount1'] = intval($supplierData['Discount1']);
                    }
                    if (!empty($supplierData['Discount2']) && intval($supplierData['Discount2']) > 0) {
                        $jsonMapping['Discount2'] = intval($supplierData['Discount2']);
                    }

                    $newSupplier['jsonToOcrSanity'] = $jsonMapping;
                }

                // Update or add supplier
                if ($mode === 'edit') {
                    $originalName = $_POST['originalHebrewName'];
                    $found = false;
                    foreach ($suppliers as $i => $supplier) {
                        if ($supplier['hebrewName'] === $originalName) {
                            $suppliers[$i] = $newSupplier;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $errors['general'] = 'ספק לא נמצא';
                    }
                } else {
                    // Check if supplier already exists
                    foreach ($suppliers as $supplier) {
                        if ($supplier['hebrewName'] === $newSupplier['hebrewName']) {
                            $errors['hebrewName'] = 'ספק עם שם זה כבר קיים';
                            break;
                        }
                    }
                    if (empty($errors)) {
                        $suppliers[] = $newSupplier;
                    }
                }

                // Save if no errors
                if (empty($errors)) {
                    if (saveSuppliers($suppliersFile, $suppliers)) {
                        $success = 'הספק נשמר בהצלחה!';
                        $mode = '';
                        $selectedSupplier = null;
                    } else {
                        $errors['general'] = 'שגיאה בשמירת הקובץ';
                    }
                } else {
                    // Keep form data for re-display
                    $selectedSupplier = $supplierData;
                }
            } else {
                // Keep form data for re-display
                $selectedSupplier = $supplierData;
            }
        }
    }
}

$suppliers = loadSuppliers($suppliersFile);
?>
<!DOCTYPE html>
<html dir="ltr" lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הגדרת ספקים</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
            direction: ltr;
            text-align: left;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .search-section {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            align-items: center;
        }

        .search-section select {
            flex: 1;
            padding: 10px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group label .required {
            color: red;
        }

        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }

        .form-group input[type="text"].error,
        .form-group select.error {
            border-color: red;
        }

        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        .success-message {
            background: #c6f6d5;
            border: 2px solid #48bb78;
            color: #22543d;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .checkbox-group {
            margin-bottom: 20px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .mapping-section {
            background: #f7fafc;
            padding: 20px;
            border-radius: 4px;
            border: 2px solid #e2e8f0;
        }

        .mapping-section h3 {
            margin-top: 0;
            color: #667eea;
        }

        .mapping-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }

        /* Searchable dropdown styles */
        .search-wrapper {
            position: relative;
            flex: 1;
        }

        #searchInput {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }

        #supplierDropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #667eea;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        #supplierDropdown.active {
            display: block;
        }

        .supplier-option {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .supplier-option:hover {
            background: #f0f4ff;
        }

        .supplier-option:last-child {
            border-bottom: none;
        }

        .no-results {
            padding: 10px;
            color: #999;
            text-align: center;
        }

        #supplierSelect {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏢 הגדרת ספקים</h1>

        <?php if ($success): ?>
            <div class="success-message">✓ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!$mode): ?>
            <!-- Search and Select Section -->
            <form method="POST" id="supplierForm">
                <div class="search-section">
                    <div class="search-wrapper">
                        <input
                            type="text"
                            id="searchInput"
                            placeholder="חפש ספק..."
                            autocomplete="off"
                        >
                        <div id="supplierDropdown"></div>
                        <select name="supplier" id="supplierSelect">
                            <option value="">בחר ספק...</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= htmlspecialchars($supplier['hebrewName']) ?>">
                                    <?= htmlspecialchars($supplier['hebrewName']) ?> (<?= htmlspecialchars($supplier['supplierName']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="action" value="edit" class="btn btn-primary" id="editBtn" disabled>ערוך</button>
                    <button type="submit" name="action" value="new" class="btn btn-success">חדש</button>
                </div>
            </form>
        <?php else: ?>
            <!-- Edit/New Form -->
            <form method="POST" id="supplierForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                <?php if ($mode === 'edit'): ?>
                    <input type="hidden" name="originalHebrewName" value="<?= htmlspecialchars($selectedSupplier['hebrewName'] ?? '') ?>">
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <div class="error-message" style="background: #fed7d7; padding: 10px; border-radius: 4px; margin-bottom: 20px;">
                        <?= htmlspecialchars($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <!-- Supplier Name (English) -->
                <div class="form-group">
                    <label>
                        שם ספק (אנגלית) <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="supplierName"
                        value="<?= htmlspecialchars($selectedSupplier['supplierName'] ?? '') ?>"
                        class="<?= isset($errors['supplierName']) ? 'error' : '' ?>"
                        placeholder="לדוגמה: Tnuva"
                    >
                    <?php if (isset($errors['supplierName'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['supplierName']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- OCR Sanity Method -->
                <div class="form-group">
                    <label>
                        שיטת OCR Sanity <span class="required">*</span>
                    </label>
                    <select
                        name="OCRsanityMethod"
                        class="<?= isset($errors['OCRsanityMethod']) ? 'error' : '' ?>"
                    >
                        <?php
                        $methods = ['Simple', 'LineTotal', 'Discount1', 'Discount2'];
                        $selectedMethod = $selectedSupplier['OCRsanityMethod'] ?? 'LineTotal';
                        foreach ($methods as $method):
                        ?>
                            <option value="<?= $method ?>" <?= $selectedMethod === $method ? 'selected' : '' ?>>
                                <?= $method ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['OCRsanityMethod'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['OCRsanityMethod']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Hebrew Name -->
                <div class="form-group">
                    <label>
                        שם ספק (עברית) <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        name="hebrewName"
                        value="<?= htmlspecialchars($selectedSupplier['hebrewName'] ?? '') ?>"
                        class="<?= isset($errors['hebrewName']) ? 'error' : '' ?>"
                        placeholder="לדוגמה: תנובה"
                    >
                    <?php if (isset($errors['hebrewName'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['hebrewName']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- JSON Mapping Checkbox -->
                <div class="checkbox-group">
                    <label>
                        <input
                            type="checkbox"
                            name="hasJsonMapping"
                            id="hasJsonMapping"
                            value="1"
                            <?= !empty($selectedSupplier['jsonToOcrSanity']) || !empty($selectedSupplier['hasJsonMapping']) ? 'checked' : '' ?>
                            onchange="toggleJsonMapping()"
                        >
                        <span>הגדר מיפוי JSON לעמודות</span>
                    </label>
                </div>

                <!-- JSON Mapping Section -->
                <div id="mappingSection" class="mapping-section" style="display: none;">
                    <h3>מיפוי עמודות JSON</h3>

                    <div class="mapping-grid">
                        <!-- Barcode (Mandatory) -->
                        <div class="form-group">
                            <label>
                                Barcode <span class="required">*</span>
                            </label>
                            <input
                                type="text"
                                name="Barcode"
                                value="<?= htmlspecialchars($selectedSupplier['jsonToOcrSanity']['Barcode'] ?? $selectedSupplier['Barcode'] ?? '') ?>"
                                class="<?= isset($errors['Barcode']) ? 'error' : '' ?>"
                                placeholder="מספר עמודה"
                            >
                            <?php if (isset($errors['Barcode'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['Barcode']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- ItemName (Mandatory) -->
                        <div class="form-group">
                            <label>
                                ItemName <span class="required">*</span>
                            </label>
                            <input
                                type="text"
                                name="ItemName"
                                value="<?= htmlspecialchars($selectedSupplier['jsonToOcrSanity']['ItemName'] ?? $selectedSupplier['ItemName'] ?? '') ?>"
                                class="<?= isset($errors['ItemName']) ? 'error' : '' ?>"
                                placeholder="מספר עמודה"
                            >
                            <?php if (isset($errors['ItemName'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['ItemName']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Qty (Mandatory) -->
                        <div class="form-group">
                            <label>
                                Qty <span class="required">*</span>
                            </label>
                            <input
                                type="text"
                                name="Qty"
                                value="<?= htmlspecialchars($selectedSupplier['jsonToOcrSanity']['Qty'] ?? $selectedSupplier['Qty'] ?? '') ?>"
                                class="<?= isset($errors['Qty']) ? 'error' : '' ?>"
                                placeholder="מספר עמודה"
                            >
                            <?php if (isset($errors['Qty'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['Qty']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- UnitPrice (Optional) -->
                        <div class="form-group">
                            <label>UnitPrice (אופציונלי)</label>
                            <input
                                type="text"
                                name="UnitPrice"
                                value="<?= htmlspecialchars($selectedSupplier['jsonToOcrSanity']['UnitPrice'] ?? $selectedSupplier['UnitPrice'] ?? '') ?>"
                                class="<?= isset($errors['UnitPrice']) ? 'error' : '' ?>"
                                placeholder="מספר עמודה"
                            >
                            <?php if (isset($errors['UnitPrice'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['UnitPrice']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- LineTotal (Optional) -->
                        <div class="form-group">
                            <label>LineTotal (אופציונלי)</label>
                            <input
                                type="text"
                                name="LineTotal"
                                value="<?= htmlspecialchars($selectedSupplier['jsonToOcrSanity']['LineTotal'] ?? $selectedSupplier['LineTotal'] ?? '') ?>"
                                class="<?= isset($errors['LineTotal']) ? 'error' : '' ?>"
                                placeholder="מספר עמודה"
                            >
                            <?php if (isset($errors['LineTotal'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['LineTotal']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Discount1 (Optional) -->
                        <div class="form-group">
                            <label>Discount1 (אופציונלי)</label>
                            <input
                                type="text"
                                name="Discount1"
                                value="<?= htmlspecialchars($selectedSupplier['jsonToOcrSanity']['Discount1'] ?? $selectedSupplier['Discount1'] ?? '') ?>"
                                class="<?= isset($errors['Discount1']) ? 'error' : '' ?>"
                                placeholder="מספר עמודה"
                            >
                            <?php if (isset($errors['Discount1'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['Discount1']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Discount2 (Optional) -->
                        <div class="form-group">
                            <label>Discount2 (אופציונלי)</label>
                            <input
                                type="text"
                                name="Discount2"
                                value="<?= htmlspecialchars($selectedSupplier['jsonToOcrSanity']['Discount2'] ?? $selectedSupplier['Discount2'] ?? '') ?>"
                                class="<?= isset($errors['Discount2']) ? 'error' : '' ?>"
                                placeholder="מספר עמודה"
                            >
                            <?php if (isset($errors['Discount2'])): ?>
                                <div class="error-message"><?= htmlspecialchars($errors['Discount2']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">💾 שמור</button>
                    <a href="?" class="btn btn-primary">ביטול</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function toggleJsonMapping() {
            const checkbox = document.getElementById('hasJsonMapping');
            const section = document.getElementById('mappingSection');
            section.style.display = checkbox.checked ? 'block' : 'none';
        }

        // Searchable dropdown functionality
        const suppliers = <?= json_encode(array_map(function($s) {
            return [
                'hebrewName' => $s['hebrewName'],
                'supplierName' => $s['supplierName'],
                'display' => $s['hebrewName'] . ' (' . $s['supplierName'] . ')'
            ];
        }, $suppliers)) ?>;

        console.log('Loaded suppliers:', suppliers);
        let selectedSupplier = null;

        function filterSuppliers(searchTerm) {
            const term = searchTerm.toLowerCase();
            return suppliers.filter(s =>
                s.hebrewName.toLowerCase().includes(term) ||
                s.supplierName.toLowerCase().includes(term)
            );
        }

        function showDropdown(filtered) {
            const dropdown = document.getElementById('supplierDropdown');
            dropdown.innerHTML = '';

            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="no-results">לא נמצאו תוצאות</div>';
            } else {
                filtered.forEach(supplier => {
                    const option = document.createElement('div');
                    option.className = 'supplier-option';
                    option.textContent = supplier.display;
                    option.onclick = function() {
                        selectSupplier(supplier);
                    };
                    dropdown.appendChild(option);
                });
            }

            dropdown.classList.add('active');
        }

        function hideDropdown() {
            setTimeout(() => {
                document.getElementById('supplierDropdown').classList.remove('active');
            }, 200);
        }

        function selectSupplier(supplier) {
            selectedSupplier = supplier;
            document.getElementById('searchInput').value = supplier.display;
            document.getElementById('supplierSelect').value = supplier.hebrewName;
            document.getElementById('editBtn').disabled = false;
            hideDropdown();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Only toggle JSON mapping if the checkbox exists (on edit/new form)
            const jsonCheckbox = document.getElementById('hasJsonMapping');
            if (jsonCheckbox) {
                toggleJsonMapping();
            }

            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                console.log('Search input found, attaching listeners');
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.trim();
                    console.log('Searching for:', searchTerm);

                    if (searchTerm === '') {
                        document.getElementById('supplierDropdown').classList.remove('active');
                        selectedSupplier = null;
                        document.getElementById('supplierSelect').value = '';
                        document.getElementById('editBtn').disabled = true;
                        return;
                    }

                    const filtered = filterSuppliers(searchTerm);
                    console.log('Filtered results:', filtered);
                    showDropdown(filtered);
                });

                searchInput.addEventListener('focus', function() {
                    if (this.value.trim() !== '') {
                        const filtered = filterSuppliers(this.value.trim());
                        showDropdown(filtered);
                    }
                });

                searchInput.addEventListener('blur', function() {
                    hideDropdown();
                });
            }
        });
    </script>
</body>
</html>
