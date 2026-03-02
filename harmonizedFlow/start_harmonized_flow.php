<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Harmonized Invoice Processing Flow</title>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            border-bottom: 4px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .workflow-diagram {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 5px solid #667eea;
            margin-bottom: 30px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #444;
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
            font-size: 15px;
        }
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        .pdf-list, .sanity-list {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            background: #fafafa;
        }
        .pdf-item, .sanity-item {
            padding: 12px;
            margin-bottom: 8px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pdf-item:hover, .sanity-item:hover {
            background: #f0f0f0;
            border-color: #667eea;
            transform: translateX(5px);
        }
        .pdf-item.selected, .sanity-item.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .pdf-item input[type="radio"], .sanity-item input[type="radio"] {
            margin-right: 10px;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .step-indicator {
            background: #667eea;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 15px;
        }
        .process-mode {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        .mode-option {
            flex: 1;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .mode-option:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .mode-option.selected {
            border-color: #667eea;
            background: #e7f3ff;
        }
        .mode-option input[type="radio"] {
            margin-right: 10px;
        }
        .mode-option label {
            cursor: pointer;
            margin: 0;
            font-size: 16px;
        }
        .mode-description {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            font-weight: normal;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Harmonized Invoice Processing Flow</h1>

        <div class="step-indicator">STEP 1: Select Processing Mode & Shop</div>

        <div class="info-box">
            <p><strong>ℹ️ About This Process:</strong></p>
            <p>This harmonized workflow guides you through the complete invoice processing pipeline:</p>
            <p><strong>PDF Selection → Crop Marking → AI OCR → Sanity Verification → Commercial Layer → Results</strong></p>
            <p><strong>OR Resume from existing OCR Sanity file for corrections/testing</strong></p>
        </div>

        <form id="flowForm" method="POST" action="">
            <!-- Processing Mode Selection -->
            <div class="form-group">
                <label>🔧 Select Processing Mode:</label>
                <div class="process-mode">
                    <div class="mode-option selected" onclick="selectMode('new')">
                        <input type="radio" name="processMode" value="new" id="mode_new" checked required>
                        <label for="mode_new">📄 New Invoice Processing</label>
                        <div class="mode-description">Start from PDF invoice file (full pipeline)</div>
                    </div>
                    <div class="mode-option" onclick="selectMode('existing')">
                        <input type="radio" name="processMode" value="existing" id="mode_existing" required>
                        <label for="mode_existing">📂 Resume from OCR Sanity File</label>
                        <div class="mode-description">Continue from existing sanity file (testing/corrections)</div>
                    </div>
                </div>
            </div>

            <!-- Shop Selection (Common for both modes) -->
            <div class="form-group">
                <label for="shopName">🏪 Select Shop:</label>
                <select name="shopName" id="shopName" class="shop-select">
                    <option value="">-- Select a Shop --</option>
                    <?php
                    $shopsFile = __DIR__ . '/../configDir/shops_V2.json';
                    if (file_exists($shopsFile)) {
                        $shops = json_decode(file_get_contents($shopsFile), true);
                        foreach ($shops as $shop) {
                            echo "<option value='{$shop['ShopName']}'>{$shop['ShopName']}</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- NEW INVOICE MODE CONTENT -->
            <div id="newInvoiceContent" class="content-section active">
                <div class="warning-box">
                    <p><strong>⚠️ PDF Naming Convention Required:</strong></p>
                    <p>Invoice PDFs in <code>preProcessDir</code> must follow one of these naming formats:</p>
                    <p><strong>"XXXX dd-mm-yy Z.pdf"</strong> &nbsp;or&nbsp; <strong>"XXXX dd-mm-yy.pdf"</strong></p>
                    <p>Where:</p>
                    <ul style="margin: 10px 0 0 20px; padding: 0;">
                        <li><strong>XXXX</strong> = Supplier name (letters/numbers)</li>
                        <li><strong>dd-mm-yy</strong> = Process date</li>
                        <li><strong>Z</strong> = Single capital letter (A-Z) for sequence (optional)</li>
                    </ul>
                    <p style="margin-top: 10px;"><strong>Examples:</strong> "Tayari 15-11-25 A.pdf", "FarmaDeal 23-11-25 B.pdf", "Tnuva 01-02-25.pdf"</p>
                </div>

                <!-- PDF Invoice Selection -->
                <div class="form-group">
                    <label>📄 Select Invoice PDF from preProcessDir:</label>
                    <div class="pdf-list" id="pdfList">
                        <?php
                        $preProcessDir = realpath(__DIR__ . '/../preProcessDir');
                        $pdfFiles = glob($preProcessDir . '/*.pdf');

                        if (empty($pdfFiles)) {
                            echo '<p style="padding: 20px; text-align: center; color: #999;">No PDF files found in preProcessDir</p>';
                        } else {
                            foreach ($pdfFiles as $pdfPath) {
                                $pdfName = basename($pdfPath);

                                // Parse filename to check format
                                // Valid formats: "XXXX dd-mm-yy Z.pdf" or "XXXX dd-mm-yy.pdf"
                                $isValidFormat = preg_match('/^(.+?)\s+(\d{2}-\d{2}-\d{2})(\s+[A-Z])?\.pdf$/i', $pdfName, $matches);

                                $statusIcon = $isValidFormat ? '✅' : '⚠️';
                                $statusColor = $isValidFormat ? '#28a745' : '#ff9800';

                                echo "<div class='pdf-item' onclick='selectPdf(this)'>";
                                echo "<input type='radio' name='pdfFile' value='{$pdfName}' id='pdf_{$pdfName}'>";
                                echo "<label for='pdf_{$pdfName}' style='display: inline; font-weight: normal; cursor: pointer; width: 100%;'>";
                                echo "<span style='color: {$statusColor}; margin-right: 5px;'>{$statusIcon}</span>";
                                echo htmlspecialchars($pdfName);
                                echo "</label>";
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- EXISTING SANITY FILE MODE CONTENT -->
            <div id="existingSanityContent" class="content-section">
                <div class="info-box">
                    <p><strong>ℹ️ OCR Sanity File Format:</strong></p>
                    <p><strong>"OCRsanity_XXXX_dd-mm-yyyy_Z_ddmmyyyy_hhmmss.xlsx"</strong></p>
                    <p>Where:</p>
                    <ul style="margin: 10px 0 0 20px; padding: 0;">
                        <li><strong>XXXX</strong> = Supplier name (any letters/numbers)</li>
                        <li><strong>dd-mm-yyyy</strong> = Process date</li>
                        <li><strong>Z</strong> = Invoice identifier (A-Z)</li>
                        <li><strong>ddmmyyyy_hhmmss</strong> = Timestamp (8 digits date + 6 digits time)</li>
                    </ul>
                    <p style="margin-top: 10px;"><strong>Example:</strong> "OCRsanity_Tayari_15-11-2025_A_15112025_143022.xlsx"</p>
                </div>

                <!-- Sanity File Selection -->
                <div class="form-group">
                    <label>📂 Select OCR Sanity File:</label>
                    <div class="sanity-list" id="sanityList">
                        <?php
                        $sanityDir = realpath(__DIR__ . '/../OCRsanity/sanity_files');
                        $sanityFiles = glob($sanityDir . '/OCRsanity_*.xlsx');

                        if (empty($sanityFiles)) {
                            echo '<p style="padding: 20px; text-align: center; color: #999;">No OCR Sanity files found in sanity_files directory</p>';
                        } else {
                            // Sort by modification time (newest first)
                            usort($sanityFiles, function($a, $b) {
                                return filemtime($b) - filemtime($a);
                            });

                            foreach ($sanityFiles as $sanityPath) {
                                $sanityName = basename($sanityPath);
                                $modTime = date('Y-m-d H:i:s', filemtime($sanityPath));

                                // Parse filename to check format
                                // Expected format: "OCRsanity_XXXX_dd-mm-yyyy_Z_ddmmyyyy_hhmmss.xlsx"
                                $isValidFormat = preg_match('/^OCRsanity_(.+?)_(\d{2}-\d{2}-\d{4})_([A-Z])_(\d{8})_(\d{6})\.xlsx$/i', $sanityName, $matches);

                                $statusIcon = $isValidFormat ? '✅' : '⚠️';
                                $statusColor = $isValidFormat ? '#28a745' : '#ff9800';

                                echo "<div class='sanity-item' onclick='selectSanity(this)'>";
                                echo "<input type='radio' name='sanityFile' value='{$sanityName}' id='sanity_{$sanityName}'>";
                                echo "<label for='sanity_{$sanityName}' style='display: inline; font-weight: normal; cursor: pointer; width: 100%;'>";
                                echo "<span style='color: {$statusColor}; margin-right: 5px;'>{$statusIcon}</span>";
                                echo htmlspecialchars($sanityName);
                                echo "<br><span style='font-size: 12px; color: #888; margin-left: 25px;'>Modified: {$modTime}</span>";
                                echo "</label>";
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <button type="submit" id="startBtn">🚀 Start Process</button>
        </form>
    </div>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Initialize Select2 for shop dropdown
        $(document).ready(function() {
            $('.shop-select').select2({
                placeholder: "-- Select a Shop --",
                allowClear: false,
                width: '100%'
            });

            // Initialize form action on page load (default is 'new' mode)
            document.getElementById('flowForm').action = 'step2_crop_launcher.php';
        });

        // Function to select processing mode
        function selectMode(mode) {
            // Update radio buttons
            document.getElementById('mode_new').checked = (mode === 'new');
            document.getElementById('mode_existing').checked = (mode === 'existing');

            // Update visual selection
            document.querySelectorAll('.mode-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');

            // Show/hide appropriate content
            if (mode === 'new') {
                document.getElementById('newInvoiceContent').classList.add('active');
                document.getElementById('existingSanityContent').classList.remove('active');

                // Update form action
                document.getElementById('flowForm').action = 'step2_crop_launcher.php';
                document.getElementById('startBtn').textContent = '🚀 Start Harmonized Flow';
            } else {
                document.getElementById('newInvoiceContent').classList.remove('active');
                document.getElementById('existingSanityContent').classList.add('active');

                // Update form action
                document.getElementById('flowForm').action = 'resume_from_sanity.php';
                document.getElementById('startBtn').textContent = '🚀 Resume from Sanity File';
            }
        }

        // Function to select PDF item
        function selectPdf(element) {
            // Remove selected class from all items
            document.querySelectorAll('.pdf-item').forEach(item => {
                item.classList.remove('selected');
            });

            // Add selected class to clicked item
            element.classList.add('selected');

            // Check the radio button
            const radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
        }

        // Function to select Sanity file item
        function selectSanity(element) {
            // Remove selected class from all items
            document.querySelectorAll('.sanity-item').forEach(item => {
                item.classList.remove('selected');
            });

            // Add selected class to clicked item
            element.classList.add('selected');

            // Check the radio button
            const radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
        }

        // Form validation
        document.getElementById('flowForm').addEventListener('submit', function(e) {
            const shopName = document.getElementById('shopName').value;
            const processMode = document.querySelector('input[name="processMode"]:checked').value;

            if (!shopName) {
                if (processMode === 'existing') {
                    alert('⚠️ Please select a shop before resuming from Sanity File!\n\nYou must choose a shop from the dropdown to continue.');
                } else {
                    alert('⚠️ Please select a shop before starting the process!\n\nYou must choose a shop from the dropdown to continue.');
                }
                e.preventDefault();
                return false;
            }

            if (processMode === 'new') {
                // Validate PDF selection
                const pdfFile = document.querySelector('input[name="pdfFile"]:checked');

                if (!pdfFile) {
                    alert('⚠️ Please select an invoice PDF');
                    e.preventDefault();
                    return false;
                }

                // Check if PDF has valid naming format
                const pdfName = pdfFile.value;
                const isValidFormat = /^(.+?)\s+(\d{2}-\d{2}-\d{2})(\s+[A-Z])?\.pdf$/i.test(pdfName);

                if (!isValidFormat) {
                    const proceed = confirm('⚠️ Warning: The selected PDF does not follow the required naming convention.\n\n' +
                                           'Expected format: "XXXX dd-mm-yy Z.pdf" or "XXXX dd-mm-yy.pdf"\n\n' +
                                           'Do you want to proceed anyway?');
                    if (!proceed) {
                        e.preventDefault();
                        return false;
                    }
                }
            } else {
                // Validate Sanity file selection
                const sanityFile = document.querySelector('input[name="sanityFile"]:checked');

                if (!sanityFile) {
                    alert('⚠️ Please select an OCR Sanity file');
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });
    </script>
</body>
</html>
