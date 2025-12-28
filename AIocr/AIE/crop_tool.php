<?php
/**
 * AI Enhancer Crop Tool
 *
 * Launches the multi_rectangle_crop_viewer for AIE PDF cropping
 * Saves crops to AIE/crops/ directory (separate from harmonized)
 */

session_start();

$pdfFile = $_GET['pdf'] ?? '';

if (empty($pdfFile)) {
    die('Error: No PDF file specified');
}

// Validate PDF exists in AIE invoice_pdf directory
$invoicePdfDir = __DIR__ . '/invoice_pdf/';
$pdfPath = $invoicePdfDir . $pdfFile;

if (!file_exists($pdfPath)) {
    die('Error: PDF file not found: ' . htmlspecialchars($pdfFile));
}

// Store in session for the save handler
$_SESSION['aie_pdf_file'] = $pdfFile;
$_SESSION['aie_mode'] = true; // Flag to indicate we're in AIE mode

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIE - Mark Crop Regions</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .badge {
            background: #ff9800;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 10px;
        }
        iframe {
            width: 100%;
            height: calc(100vh - 60px);
            border: none;
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
        .loading-badge {
            background: #ff9800;
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .dots {
            display: inline-block;
            width: 20px;
        }
    </style>
    <script>
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

        // Listen for message from iframe to show loading overlay
        window.addEventListener('message', function(event) {
            if (event.data === 'showLoading') {
                document.getElementById('loadingOverlay').classList.add('active');
                startTimer();
            }
        });
    </script>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>🧪 AI Enhancer<span class="loading-badge">EXPERIMENTAL</span></h3>
            <p>Processing invoice with OpenAI<span class="dots" id="dots">...</span></p>
            <p>Please wait while GPT-4.1-mini analyzes your cropped images</p>
            <div class="timer" id="timer">0s</div>
        </div>
    </div>

    <div class="header">
        <h1>🧪 AI Enhancer - Mark Crop Regions<span class="badge">EXPERIMENTAL</span></h1>
        <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">
            PDF: <?php echo htmlspecialchars($pdfFile); ?>
        </p>
    </div>

    <!-- Embed the AIE-specific crop viewer -->
    <iframe src="aie_crop_viewer.html?pdf=<?php echo urlencode('invoice_pdf/' . $pdfFile); ?>"></iframe>
</body>
</html>
