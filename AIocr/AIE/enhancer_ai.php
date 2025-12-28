<?php
/**
 * AI Enhancer (AIE) - Testing Tool for OCR Improvements
 *
 * Purpose: Test new OCR approaches with OpenAI LLM
 * This is a SEPARATE experimental tool - does NOT affect harmonized process
 *
 * Features:
 * - Select PDF from invoice_pdf directory or upload new PDF
 * - Upload PNG crops
 * - Crop PDF to create PNGs (like harmonizer)
 * - Send to OpenAI with configurable prompt/settings from AIE_config.json
 * - Display JSON response and save to AIE/ocr_extracted directory
 */

session_start();

// Handle PNG crops upload FIRST - before any HTML output (to allow header redirects)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cropFiles']) && !empty($_FILES['cropFiles']['name'][0])) {
    // Save uploaded PNG files to crops directory
    $uploadedFiles = $_FILES['cropFiles'];
    $fileCount = count($uploadedFiles['name']);

    $cropsDir = __DIR__ . '/crops/';
    if (!file_exists($cropsDir)) {
        mkdir($cropsDir, 0755, true);
    }

    $savedCrops = [];
    $cropFilesPaths = [];

    // Extract basename from first file
    $firstFileName = $uploadedFiles['name'][0];
    if (preg_match('/^(.+?)_(InvoiceNo|Date|Table\d+|Total)\.png$/i', $firstFileName, $matches)) {
        $baseName = $matches[1];
    } else {
        $baseName = pathinfo($firstFileName, PATHINFO_FILENAME);
        $baseName = preg_replace('/_(InvoiceNo|Date|Table\d+|Total)$/i', '', $baseName);
    }

    // Save all uploaded files to crops directory
    for ($i = 0; $i < $fileCount; $i++) {
        if ($uploadedFiles['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = $uploadedFiles['name'][$i];
            $tmpName = $uploadedFiles['tmp_name'][$i];

            // Use original filename or add basename prefix if missing
            if (strpos($fileName, $baseName) === 0) {
                $targetFileName = $fileName;
            } else {
                $targetFileName = $baseName . '_' . $fileName;
            }

            $targetPath = $cropsDir . $targetFileName;

            if (move_uploaded_file($tmpName, $targetPath)) {
                $savedCrops[] = $targetFileName;
                $cropFilesPaths[] = $targetPath;
            }
        }
    }

    if (!empty($savedCrops)) {
        // Store in session for processing
        $_SESSION['aie_crop_files'] = $cropFilesPaths;
        $_SESSION['aie_crop_names'] = $savedCrops;
        $_SESSION['aie_pdf_basename'] = $baseName;
        $_SESSION['aie_mode'] = true;

        // Redirect to unified processing page
        header('Location: process_crops_from_pdf.php');
        exit;
    }
    // If saving failed, continue to show error in HTML section below
}

// Determine which step we're in
$step = $_GET['step'] ?? 'upload';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Enhancer (AIE) - OCR Testing Tool</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            border-bottom: 4px solid #1e3c72;
            padding-bottom: 15px;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .badge {
            background: #ff9800;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
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
        input[type="file"] {
            display: block;
            width: 100%;
            padding: 10px;
            border: 2px dashed #ccc;
            border-radius: 4px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        input[type="file"]:hover {
            border-color: #1e3c72;
        }
        button, .btn {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        button:hover, .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.4);
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .upload-option {
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        .upload-option:hover {
            border-color: #1e3c72;
            background: #f8f9fa;
        }
        .upload-option.selected {
            border-color: #1e3c72;
            background: #e3f2fd;
        }
        .upload-option input[type="radio"] {
            margin-right: 10px;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
        .pdf-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            background: #fafafa;
            margin-bottom: 15px;
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
            border-color: #1e3c72;
            transform: translateX(5px);
        }
        .pdf-item.selected {
            background: #1e3c72;
            color: white;
            border-color: #1e3c72;
        }
        .pdf-item input[type="radio"] {
            margin-right: 10px;
        }
        .pdf-source-option {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .pdf-source-option:hover {
            border-color: #1e3c72;
            background: #f8f9fa;
        }
        .pdf-source-option.selected {
            border-color: #1e3c72;
            background: #e3f2fd;
        }
        .pdf-source-option input[type="radio"] {
            margin-right: 10px;
        }

        /* Loading overlay styles */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-content {
            background: white;
            padding: 60px 80px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 500px;
        }
        .spinner {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            border: 8px solid #f3f3f3;
            border-top: 8px solid #2a5298;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-content h3 {
            color: #1e3c72;
            margin: 0 0 20px 0;
            font-size: 28px;
        }
        .loading-content p {
            color: #666;
            margin: 10px 0;
            font-size: 16px;
            line-height: 1.6;
        }
        .loading-content .timer {
            font-size: 24px;
            color: #2a5298;
            font-weight: bold;
            margin-top: 20px;
        }
        .dots {
            display: inline-block;
            width: 20px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>🧪 AI Enhancer<span class="badge">EXPERIMENTAL</span></h3>
            <p>Processing invoice with OpenAI<span class="dots" id="dots">...</span></p>
            <p>Please wait while GPT-4.1-mini analyzes your cropped images</p>
            <div class="timer" id="timer">0s</div>
        </div>
    </div>

    <div class="container">
        <h1>🧪 AI Enhancer (AIE)<span class="badge">EXPERIMENTAL</span></h1>

<?php if ($step === 'upload'): ?>

        <div class="info-box">
            <p><strong>ℹ️ About AI Enhancer:</strong></p>
            <p>This is an <strong>experimental testing tool</strong> for improving OCR accuracy with OpenAI.</p>
            <p>It is completely separate from the production harmonized process.</p>
            <p><strong>Configuration:</strong> Prompt and model settings are loaded from <code>AIE_config.json</code></p>
            <p><strong>Directories:</strong></p>
            <ul style="margin: 5px 0 0 20px;">
                <li>PDFs: <code>AIE/invoice_pdf/</code></li>
                <li>Crops: <code>AIE/crops/</code></li>
                <li>Results: <code>AIE/ocr_extracted/</code></li>
            </ul>
        </div>

        <div class="warning-box">
            <p><strong>⚠️ Important:</strong> This tool is for testing purposes only. Changes here will NOT affect the harmonized process.</p>
        </div>

        <form id="uploadForm" method="POST" action="enhancer_ai.php?step=process" enctype="multipart/form-data">
            <!-- Upload Mode Selection -->
            <div class="form-group">
                <label>📁 Select Input Type:</label>

                <div class="upload-option selected" onclick="selectMode('pdf')">
                    <input type="radio" name="inputMode" value="pdf" id="mode_pdf" checked required>
                    <label for="mode_pdf" style="display: inline; cursor: pointer; font-weight: bold;">📄 PDF Invoice</label>
                    <p style="margin: 8px 0 0 30px; font-size: 13px; color: #666;">Select existing PDF or upload new one, then mark crop regions</p>
                </div>

                <div class="upload-option" onclick="selectMode('crops')">
                    <input type="radio" name="inputMode" value="crops" id="mode_crops" required>
                    <label for="mode_crops" style="display: inline; cursor: pointer; font-weight: bold;">🖼️ PNG Crops (Already Prepared)</label>
                    <p style="margin: 8px 0 0 30px; font-size: 13px; color: #666;">Upload pre-cropped PNG files (InvoiceNo, Date, Table1, Total)</p>
                </div>
            </div>

            <!-- PDF Upload Section -->
            <div id="pdfSection" class="content-section active">
                <!-- PDF Source Selection -->
                <div class="form-group">
                    <label>📂 PDF Source:</label>

                    <div class="pdf-source-option selected" onclick="selectPdfSource('existing')">
                        <input type="radio" name="pdfSource" value="existing" id="pdf_existing" checked>
                        <label for="pdf_existing" style="display: inline; cursor: pointer; font-weight: bold;">Select from invoice_pdf directory</label>
                    </div>

                    <div class="pdf-source-option" onclick="selectPdfSource('upload')">
                        <input type="radio" name="pdfSource" value="upload" id="pdf_upload">
                        <label for="pdf_upload" style="display: inline; cursor: pointer; font-weight: bold;">Upload new PDF</label>
                    </div>
                </div>

                <!-- Existing PDF Selection -->
                <div id="existingPdfSection" class="content-section active">
                    <div class="form-group">
                        <label>📄 Select PDF from invoice_pdf directory:</label>
                        <div class="pdf-list" id="pdfList">
                            <?php
                            $invoicePdfDir = __DIR__ . '/invoice_pdf/';
                            $pdfFiles = glob($invoicePdfDir . '/*.pdf');

                            if (empty($pdfFiles)) {
                                echo '<p style="padding: 20px; text-align: center; color: #999;">No PDF files found in invoice_pdf directory.<br>Place PDF files in AIE/invoice_pdf/ or upload a new one.</p>';
                            } else {
                                // Sort by modification time (newest first)
                                usort($pdfFiles, function($a, $b) {
                                    return filemtime($b) - filemtime($a);
                                });

                                foreach ($pdfFiles as $pdfPath) {
                                    $pdfName = basename($pdfPath);
                                    $modTime = date('Y-m-d H:i:s', filemtime($pdfPath));
                                    $fileSize = round(filesize($pdfPath) / 1024, 1); // KB

                                    echo "<div class='pdf-item' onclick='selectPdf(this)'>";
                                    echo "<input type='radio' name='existingPdf' value='{$pdfName}' id='pdf_{$pdfName}'>";
                                    echo "<label for='pdf_{$pdfName}' style='display: inline; font-weight: normal; cursor: pointer; width: 100%;'>";
                                    echo htmlspecialchars($pdfName);
                                    echo "<br><span style='font-size: 12px; color: #888; margin-left: 25px;'>Modified: {$modTime} | Size: {$fileSize} KB</span>";
                                    echo "</label>";
                                    echo "</div>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- New PDF Upload -->
                <div id="uploadPdfSection" class="content-section">
                    <div class="form-group">
                        <label for="pdfFile">📤 Upload New PDF Invoice:</label>
                        <input type="file" id="pdfFile" name="pdfFile" accept=".pdf,application/pdf">
                        <small style="display:block; margin-top:10px; color:#666;">PDF will be saved to AIE/invoice_pdf/ directory</small>
                    </div>
                </div>
            </div>

            <!-- PNG Crops Upload Section -->
            <div id="cropsSection" class="content-section">
                <div class="info-box">
                    <p><strong>ℹ️ PNG File Naming:</strong></p>
                    <p>Files must contain these suffixes (case-insensitive):</p>
                    <p>• <strong>InvoiceNo</strong> - Invoice number crop</p>
                    <p>• <strong>Date</strong> - Invoice date crop</p>
                    <p>• <strong>Table1</strong> - First table section (Table2, Table3... for multi-page tables)</p>
                    <p>• <strong>Total</strong> - Invoice total amount</p>
                    <p style="margin-top: 10px;"><strong>Example:</strong> invoice_InvoiceNo.png, invoice_Table1.png</p>
                </div>

                <div class="form-group">
                    <label for="cropFiles">🖼️ Upload PNG Crop Files (Minimum 4):</label>
                    <input type="file" id="cropFiles" name="cropFiles[]" accept=".png,image/png" multiple>
                    <small style="display:block; margin-top:10px; color:#666;">Hold Ctrl/Cmd to select multiple files</small>
                    <div id="selectedFiles" style="margin-top:15px; padding:10px; background:#f0f8ff; border-radius:5px; display:none;">
                        <strong>Selected files:</strong>
                        <ul id="fileList" style="margin:10px 0 0 0; padding-left:20px; list-style:disc;"></ul>
                    </div>
                </div>
            </div>

            <button type="submit" id="submitBtn">🚀 Continue</button>
        </form>

<?php elseif ($step === 'process'): ?>

        <?php
        // Handle existing PDF selection - redirect to cropping tool
        if (isset($_POST['existingPdf']) && !empty($_POST['existingPdf'])) {
            $pdfFileName = $_POST['existingPdf'];
            $invoicePdfDir = __DIR__ . '/invoice_pdf/';
            $pdfPath = $invoicePdfDir . $pdfFileName;

            if (file_exists($pdfPath)) {
                // Store PDF info in session
                $_SESSION['aie_pdf_file'] = $pdfFileName;

                echo "<div class='success-box'>";
                echo "<p><strong>✅ PDF Selected!</strong></p>";
                echo "<p>File: " . htmlspecialchars($pdfFileName) . "</p>";
                echo "<p>Redirecting to crop marking tool...</p>";
                echo "</div>";

                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'crop_tool.php?pdf=" . urlencode($pdfFileName) . "';
                    }, 2000);
                </script>";
            } else {
                echo "<div class='warning-box'>";
                echo "<p><strong>⚠️ Error:</strong> PDF file not found in invoice_pdf directory</p>";
                echo "</div>";
                echo "<a href='enhancer_ai.php' class='btn'>← Go Back</a>";
            }
        }
        // Handle new PDF upload - save to invoice_pdf and redirect to cropping tool
        elseif (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['pdfFile']['tmp_name'];
            $originalName = $_FILES['pdfFile']['name'];

            // Save PDF to invoice_pdf directory
            $invoicePdfDir = __DIR__ . '/invoice_pdf/';
            if (!file_exists($invoicePdfDir)) {
                mkdir($invoicePdfDir, 0755, true);
            }

            $timestamp = date('dmY_His');
            $pdfFileName = 'AIE_' . $timestamp . '.pdf';
            $pdfPath = $invoicePdfDir . $pdfFileName;

            if (move_uploaded_file($tmpName, $pdfPath)) {
                // Store PDF info in session
                $_SESSION['aie_pdf_file'] = $pdfFileName;

                echo "<div class='success-box'>";
                echo "<p><strong>✅ PDF Uploaded Successfully!</strong></p>";
                echo "<p>Original: " . htmlspecialchars($originalName) . "</p>";
                echo "<p>Saved as: " . htmlspecialchars($pdfFileName) . "</p>";
                echo "<p>Location: AIE/invoice_pdf/</p>";
                echo "<p>Redirecting to crop marking tool...</p>";
                echo "</div>";

                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'crop_tool.php?pdf=" . urlencode($pdfFileName) . "';
                    }, 2000);
                </script>";
            } else {
                echo "<div class='warning-box'>";
                echo "<p><strong>⚠️ Error:</strong> Failed to save PDF file</p>";
                echo "</div>";
                echo "<a href='enhancer_ai.php' class='btn'>← Go Back</a>";
            }
        }
        // Handle PNG crops upload error (actual upload handling is at top of file before HTML)
        elseif (isset($_FILES['cropFiles']) && !empty($_FILES['cropFiles']['name'][0])) {
            // If we reach here, it means the upload failed (handled at top of file)
            echo "<div class='warning-box'>";
            echo "<p><strong>⚠️ Error:</strong> Failed to save crop files</p>";
            echo "</div>";
            echo "<a href='enhancer_ai.php' class='btn'>← Go Back</a>";
        }
        else {
            echo "<div class='warning-box'>";
            echo "<p><strong>⚠️ Error:</strong> No files were selected or uploaded</p>";
            echo "</div>";
            echo "<a href='enhancer_ai.php' class='btn'>← Go Back</a>";
        }
        ?>

<?php endif; ?>

    </div>

    <script>
        function selectMode(mode) {
            // Update radio buttons
            document.getElementById('mode_pdf').checked = (mode === 'pdf');
            document.getElementById('mode_crops').checked = (mode === 'crops');

            // Update visual selection
            document.querySelectorAll('.upload-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');

            // Show/hide appropriate sections
            if (mode === 'pdf') {
                document.getElementById('pdfSection').classList.add('active');
                document.getElementById('cropsSection').classList.remove('active');

                // Enable/disable file inputs
                document.getElementById('cropFiles').required = false;
            } else {
                document.getElementById('pdfSection').classList.remove('active');
                document.getElementById('cropsSection').classList.add('active');

                // Enable/disable file inputs
                document.getElementById('cropFiles').required = true;
            }
        }

        function selectPdfSource(source) {
            // Update radio buttons
            document.getElementById('pdf_existing').checked = (source === 'existing');
            document.getElementById('pdf_upload').checked = (source === 'upload');

            // Update visual selection
            document.querySelectorAll('.pdf-source-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');

            // Show/hide appropriate sections
            if (source === 'existing') {
                document.getElementById('existingPdfSection').classList.add('active');
                document.getElementById('uploadPdfSection').classList.remove('active');

                // Clear file upload
                document.getElementById('pdfFile').value = '';
            } else {
                document.getElementById('existingPdfSection').classList.remove('active');
                document.getElementById('uploadPdfSection').classList.add('active');

                // Uncheck existing PDF selections
                document.querySelectorAll('input[name="existingPdf"]').forEach(radio => {
                    radio.checked = false;
                });
                document.querySelectorAll('.pdf-item').forEach(item => {
                    item.classList.remove('selected');
                });
            }
        }

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

        // Display selected crop files
        document.getElementById('cropFiles').addEventListener('change', function(e) {
            const files = e.target.files;
            const fileListDiv = document.getElementById('selectedFiles');
            const fileList = document.getElementById('fileList');

            if (files.length > 0) {
                // Clear previous list
                fileList.innerHTML = '';

                // Add each filename to the list
                Array.from(files).forEach(file => {
                    const li = document.createElement('li');
                    li.textContent = file.name;
                    li.style.color = '#1e3c72';
                    li.style.marginBottom = '5px';
                    fileList.appendChild(li);
                });

                // Show the file list container
                fileListDiv.style.display = 'block';
            } else {
                // Hide if no files selected
                fileListDiv.style.display = 'none';
            }
        });

        // Loading overlay timer functions
        let startTime;
        let timerInterval;

        function startTimer() {
            startTime = Date.now();
            updateTimer();
            timerInterval = setInterval(updateTimer, 1000);

            // Animate dots
            let dotCount = 0;
            setInterval(() => {
                dotCount = (dotCount + 1) % 4;
                const dotsEl = document.getElementById('dots');
                if (dotsEl) {
                    dotsEl.textContent = '.'.repeat(dotCount);
                }
            }, 500);
        }

        function updateTimer() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const timerEl = document.getElementById('timer');
            if (timerEl) {
                timerEl.textContent = elapsed + 's';
            }
        }

        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const mode = document.querySelector('input[name="inputMode"]:checked').value;

            if (mode === 'pdf') {
                const pdfSource = document.querySelector('input[name="pdfSource"]:checked').value;

                if (pdfSource === 'existing') {
                    const existingPdf = document.querySelector('input[name="existingPdf"]:checked');
                    if (!existingPdf) {
                        alert('⚠️ Please select a PDF file from the list');
                        e.preventDefault();
                        return false;
                    }
                } else {
                    const pdfFile = document.getElementById('pdfFile').files[0];
                    if (!pdfFile) {
                        alert('⚠️ Please select a PDF file to upload');
                        e.preventDefault();
                        return false;
                    }
                }
            } else {
                const cropFiles = document.getElementById('cropFiles').files;
                if (cropFiles.length < 4) {
                    alert('⚠️ Please select at least 4 PNG files (InvoiceNo, Date, Table1, Total)');
                    e.preventDefault();
                    return false;
                }

                // Show loading overlay for PNG crops processing
                document.getElementById('loadingOverlay').classList.add('active');
                startTimer();
            }

            return true;
        });
    </script>
</body>
</html>
