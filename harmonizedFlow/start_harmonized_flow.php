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
        .pdf-list {
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            background: #fafafa;
        }
        .pdf-item {
            padding: 12px;
            margin-bottom: 8px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pdf-item:hover {
            background: #f0f0f0;
            border-color: #667eea;
            transform: translateX(5px);
        }
        .pdf-item.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .pdf-item input[type="radio"] {
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Harmonized Invoice Processing Flow</h1>

        <div class="step-indicator">STEP 1: Select PDF Invoice & Shop</div>

        <div class="info-box">
            <p><strong>ℹ️ About This Process:</strong></p>
            <p>This harmonized workflow guides you through the complete invoice processing pipeline:</p>
            <p><strong>PDF Selection → Crop Marking → AI OCR → Sanity Verification → Commercial Layer → Results</strong></p>
        </div>

        <div class="warning-box">
            <p><strong>⚠️ PDF Naming Convention Required:</strong></p>
            <p>Invoice PDFs in <code>preProcessDir</code> must follow this naming format:</p>
            <p><strong>"XXXX dd-mm-yy Z.pdf"</strong></p>
            <p>Where:</p>
            <ul style="margin: 10px 0 0 20px; padding: 0;">
                <li><strong>XXXX</strong> = Supplier name (letters/numbers)</li>
                <li><strong>dd-mm-yy</strong> = Process date</li>
                <li><strong>Z</strong> = Single capital letter (A-Z) for sequence</li>
            </ul>
            <p style="margin-top: 10px;"><strong>Examples:</strong> "Tayari 15-11-25 A.pdf", "FarmaDeal 23-11-25 B.pdf"</p>
        </div>

        <form id="flowForm" method="POST" action="step2_crop_launcher.php">
            <!-- Shop Selection -->
            <div class="form-group">
                <label for="shopName">🏪 Select Shop:</label>
                <select name="shopName" id="shopName" required class="shop-select">
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
                            // Expected format: "XXXX dd-mm-yy Z.pdf"
                            $isValidFormat = preg_match('/^(.+?)\s+(\d{2}-\d{2}-\d{2})\s+([A-Z])\.pdf$/i', $pdfName, $matches);

                            $statusIcon = $isValidFormat ? '✅' : '⚠️';
                            $statusColor = $isValidFormat ? '#28a745' : '#ff9800';

                            echo "<div class='pdf-item' onclick='selectPdf(this)'>";
                            echo "<input type='radio' name='pdfFile' value='{$pdfName}' id='pdf_{$pdfName}' required>";
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

            <button type="submit" id="startBtn">🚀 Start Harmonized Flow</button>
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
        });

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

        // Form validation
        document.getElementById('flowForm').addEventListener('submit', function(e) {
            const shopName = document.getElementById('shopName').value;
            const pdfFile = document.querySelector('input[name="pdfFile"]:checked');

            if (!shopName) {
                alert('⚠️ Please select a shop');
                e.preventDefault();
                return false;
            }

            if (!pdfFile) {
                alert('⚠️ Please select an invoice PDF');
                e.preventDefault();
                return false;
            }

            // Check if PDF has valid naming format
            const pdfName = pdfFile.value;
            const isValidFormat = /^(.+?)\s+(\d{2}-\d{2}-\d{2})\s+([A-Z])\.pdf$/i.test(pdfName);

            if (!isValidFormat) {
                const proceed = confirm('⚠️ Warning: The selected PDF does not follow the required naming convention.\n\n' +
                                       'Expected format: "XXXX dd-mm-yy Z.pdf"\n\n' +
                                       'Do you want to proceed anyway?');
                if (!proceed) {
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });
    </script>
</body>
</html>
