<?php
/**
 * Step 2: Parse PDF Filename and Launch Crop Marking Tool
 *
 * This step:
 * 1. Receives PDF filename and shop name from Step 1
 * 2. Parses PDF filename to extract: Supplier Name, Process Date, Sequence Identifier
 * 3. Stores flow state in session
 * 4. Launches multi_rectangle_crop_viewer.html with the selected PDF
 */

session_start();

// Get POST data from Step 1
$pdfFile = $_POST['pdfFile'] ?? '';
$shopName = $_POST['shopName'] ?? '';

if (empty($pdfFile) || empty($shopName)) {
    die('Error: Missing required parameters (pdfFile or shopName)');
}

// Validate PDF file exists
$preProcessDir = realpath(__DIR__ . '/../preProcessDir');
$pdfPath = $preProcessDir . DIRECTORY_SEPARATOR . $pdfFile;

if (!file_exists($pdfPath)) {
    die('Error: PDF file not found: ' . htmlspecialchars($pdfFile));
}

// Parse PDF filename
// Expected format: "XXXX dd-mm-yy Z.pdf"
// Where:
//   XXXX = Supplier name (can contain spaces, letters, numbers)
//   dd-mm-yy = Process date
//   Z = Sequence identifier (single capital letter)

$supplierName = '';
$processDate = '';
$sequenceIdentifier = '';
$parseSuccess = false;

// Regex pattern: captures supplier name, date (dd-mm-yy), and letter
if (preg_match('/^(.+?)\s+(\d{2}-\d{2}-\d{2})\s+([A-Z])\.pdf$/i', $pdfFile, $matches)) {
    $supplierName = trim($matches[1]);
    $processDate = $matches[2]; // dd-mm-yy format
    $sequenceIdentifier = strtoupper($matches[3]); // Ensure uppercase
    $parseSuccess = true;
}

// If parsing failed, try to extract what we can and warn the user
if (!$parseSuccess) {
    // Try alternate pattern without sequence identifier
    if (preg_match('/^(.+?)\s+(\d{2}-\d{2}-\d{2})\.pdf$/i', $pdfFile, $matches)) {
        $supplierName = trim($matches[1]);
        $processDate = $matches[2];
        $sequenceIdentifier = 'A'; // Default to 'A'
        $parseSuccess = true;
    } else {
        // Last resort: use filename without extension as supplier name
        $supplierName = pathinfo($pdfFile, PATHINFO_FILENAME);
        $processDate = date('d-m-y');
        $sequenceIdentifier = 'A';
    }
}

// Convert process date from dd-mm-yy to dd-mm-yyyy for OCR processing
$dateObj = DateTime::createFromFormat('d-m-y', $processDate);
if ($dateObj) {
    $processDateFull = $dateObj->format('d-m-Y'); // dd-mm-yyyy format
} else {
    $processDateFull = date('d-m-Y');
}

// Store flow state in session
$_SESSION['harmonizedFlow'] = [
    'pdfFile' => $pdfFile,
    'pdfPath' => $pdfPath,
    'shopName' => $shopName,
    'supplierName' => $supplierName,
    'processDate' => $processDate, // dd-mm-yy
    'processDateFull' => $processDateFull, // dd-mm-yyyy
    'sequenceIdentifier' => $sequenceIdentifier,
    'step' => 2, // Current step
    'startTime' => time()
];

// Get shop configuration for later use
$shopsFile = __DIR__ . '/../configDir/shops_V2.json';
if (file_exists($shopsFile)) {
    $shops = json_decode(file_get_contents($shopsFile), true);
    foreach ($shops as $shop) {
        if ($shop['ShopName'] === $shopName) {
            $_SESSION['harmonizedFlow']['shopConfig'] = $shop;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 2: PDF Metadata Parsed</title>
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
        .metadata-box {
            background: #f8f9fa;
            border-left: 5px solid #28a745;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .metadata-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .metadata-label {
            font-weight: bold;
            color: #555;
            min-width: 200px;
        }
        .metadata-value {
            color: #333;
            font-family: 'Courier New', monospace;
            background: white;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .info-box {
            background: #e7f3ff;
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
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
            display: inline-block;
            text-decoration: none;
            text-align: center;
            margin-right: 10px;
        }
        button:hover, a.button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .button-group {
            margin-top: 30px;
            display: flex;
            gap: 15px;
        }
        .success-icon {
            color: #28a745;
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>Step 2: PDF Metadata Extracted</h1>

        <div class="step-indicator">STEP 2/8: Metadata Parsing Complete</div>

        <?php if (!preg_match('/^(.+?)\s+(\d{2}-\d{2}-\d{2})\s+([A-Z])\.pdf$/i', $pdfFile)): ?>
        <div class="warning-box">
            <p><strong>⚠️ Non-standard filename format detected</strong></p>
            <p>The PDF filename doesn't follow the expected format. Default values have been assigned.</p>
        </div>
        <?php endif; ?>

        <div class="metadata-box">
            <h3 style="margin-top: 0;">📋 Extracted Metadata:</h3>

            <div class="metadata-item">
                <div class="metadata-label">📄 PDF File:</div>
                <div class="metadata-value"><?php echo htmlspecialchars($pdfFile); ?></div>
            </div>

            <div class="metadata-item">
                <div class="metadata-label">🏪 Shop Name:</div>
                <div class="metadata-value"><?php echo htmlspecialchars($shopName); ?></div>
            </div>

            <div class="metadata-item">
                <div class="metadata-label">🏢 Supplier Name:</div>
                <div class="metadata-value"><?php echo htmlspecialchars($supplierName); ?></div>
            </div>

            <div class="metadata-item">
                <div class="metadata-label">📅 Process Date:</div>
                <div class="metadata-value"><?php echo htmlspecialchars($processDateFull); ?></div>
            </div>

            <div class="metadata-item">
                <div class="metadata-label">🔤 Sequence Identifier:</div>
                <div class="metadata-value"><?php echo htmlspecialchars($sequenceIdentifier); ?></div>
            </div>
        </div>

        <div class="info-box">
            <p><strong>📍 Next Step: Mark Invoice Crop Regions</strong></p>
            <p>You will now launch the crop marking tool to select regions from the PDF:</p>
            <ul style="margin: 10px 0 0 20px;">
                <li><strong>InvoiceNo</strong> - Invoice number region</li>
                <li><strong>Date</strong> - Invoice date region</li>
                <li><strong>Table1, Table2, ...</strong> - Item table regions</li>
                <li><strong>Total</strong> - Invoice total region</li>
            </ul>
            <p style="margin-top: 15px;">After marking all regions and clicking "Save Cropped Images to Server", you'll be redirected to the OCR processing step.</p>
        </div>

        <div class="button-group">
            <button onclick="launchCropTool()">📐 Launch Crop Marking Tool</button>
            <button onclick="history.back()" style="background: #6c757d;">← Back to Step 1</button>
        </div>
    </div>

    <script>
        function launchCropTool() {
            // Open crop marking tool in new window/tab with PDF path
            const pdfFile = <?php echo json_encode($pdfFile); ?>;
            const cropToolUrl = '../AIocr/multi_rectangle_crop_viewer.html?pdf=' + encodeURIComponent('../preProcessDir/' + pdfFile) + '&harmonized=true';

            // Open in same window (user will return after saving crops)
            window.location.href = cropToolUrl;
        }

        // Auto-launch after 2 seconds
        setTimeout(function() {
            launchCropTool();
        }, 2000);
    </script>
</body>
</html>
