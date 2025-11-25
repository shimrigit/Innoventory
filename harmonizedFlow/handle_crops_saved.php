<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crops Saved - Processing OCR</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .step-indicator {
            background: #667eea;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 20px;
        }
        .success-icon {
            color: #28a745;
            font-size: 64px;
            margin-bottom: 20px;
        }
        .loading {
            margin: 30px 0;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .progress-text {
            color: #666;
            margin-top: 20px;
            font-size: 16px;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>Crop Regions Saved Successfully</h1>

        <div class="step-indicator">STEP 3/8: Crops Saved, Processing OCR</div>

        <div class="info-box">
            <p><strong>✓ Crop files saved to:</strong> <code>AIocr/crops/</code></p>
            <p><strong>→ Next step:</strong> Sending crops to OpenAI for OCR processing</p>
        </div>

        <div class="loading">
            <div class="spinner"></div>
            <p class="progress-text">Processing OCR with OpenAI...</p>
            <p class="progress-text" style="font-size: 14px; color: #999;">Estimated: 1-2 minutes</p>
        </div>
    </div>

    <script>
        // Auto-redirect to OCR processing after 2 seconds
        setTimeout(function() {
            window.location.href = 'step3_process_ocr.php';
        }, 2000);
    </script>
</body>
</html>
