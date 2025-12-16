<?php
/**
 * Step 5: Commercial Layer Processing (with Auto-file Detection)
 *
 * This step:
 * 1. Gets shop name from session
 * 2. Finds the latest OCRsanity file from sanity_files directory
 * 3. Finds the latest price list file matching the shop name
 * 4. Gets the invoice PDF from session
 * 5. Calls process_commercial_layer.php to process Commercial Layer
 */

session_start();

// Check if harmonized flow session exists
if (!isset($_SESSION['harmonizedFlow'])) {
    die('Error: Harmonized flow session not found. Please start from Step 1.');
}

$flowData = $_SESSION['harmonizedFlow'];

// 1. Get shop name from session
$shopName = $flowData['shopName'];

// 2. Find the latest OCRsanity file from sanity_files directory
$sanityFilesDir = __DIR__ . '/../OCRsanity/sanity_files/';
$allSanityFiles = glob($sanityFilesDir . 'OCRsanity*.xlsx');

if (empty($allSanityFiles)) {
    die('Error: No OCRsanity files found in sanity_files directory.');
}

// Sort by modification time (newest first)
usort($allSanityFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Get the most recent OCRsanity file
$latestOcrSanityPath = $allSanityFiles[0];
$latestOcrSanityFile = basename($latestOcrSanityPath);

// 3. Find the latest price list file matching the shop name
$priceListDir = __DIR__ . '/../commercialLayer/price_list_files/';

// Verify directory exists
if (!is_dir($priceListDir)) {
    die("Error: Price list directory not found: " . htmlspecialchars($priceListDir));
}

// Search for price list files that contain the shop name in their filename
$allPriceListFiles = glob($priceListDir . '*.xlsx');

if (empty($allPriceListFiles)) {
    die("Error: No price list files (.xlsx) found in price_list_files directory: " . htmlspecialchars($priceListDir));
}

$matchingPriceListFiles = [];

foreach ($allPriceListFiles as $file) {
    $fileName = basename($file);
    // Check if shop name appears in the filename (case-insensitive)
    if (stripos($fileName, $shopName) !== false) {
        $matchingPriceListFiles[] = $file;
    }
}

if (empty($matchingPriceListFiles)) {
    // Show available files for debugging
    $availableFiles = array_map('basename', $allPriceListFiles);
    die("Error: No price list file found for shop '{$shopName}' in price_list_files directory.<br><br>" .
        "Available price list files:<br>- " . implode('<br>- ', $availableFiles) . "<br><br>" .
        "Note: The shop name must appear in the filename.");
}

// Sort by modification time (newest first)
usort($matchingPriceListFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Get the most recent matching price list file
$latestPriceListPath = $matchingPriceListFiles[0];
$latestPriceListFile = basename($latestPriceListPath);

// 4. Get the invoice PDF from session (may not exist if user skipped PDF)
$invoicePdfPath = $flowData['pdfPath'] ?? null;
$invoicePdfFile = $flowData['pdfFile'] ?? 'NOT_FOUND.pdf';

// Verify all files exist
if (!file_exists($latestOcrSanityPath)) {
    die('Error: OCRsanity file not found: ' . htmlspecialchars($latestOcrSanityPath));
}

if (!file_exists($latestPriceListPath)) {
    die('Error: Price list file not found: ' . htmlspecialchars($latestPriceListPath));
}

// PDF is optional - only verify if path is provided
if ($invoicePdfPath && !file_exists($invoicePdfPath)) {
    die('Error: Invoice PDF file not found: ' . htmlspecialchars($invoicePdfPath));
}

// 5. Simulate file uploads for process_commercial_layer.php
$_FILES['ocrSanity'] = [
    'name' => $latestOcrSanityFile,
    'tmp_name' => $latestOcrSanityPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($latestOcrSanityPath),
    'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$_FILES['priceList'] = [
    'name' => $latestPriceListFile,
    'tmp_name' => $latestPriceListPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($latestPriceListPath),
    'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

// Only set invoice file if PDF exists
if ($invoicePdfPath && file_exists($invoicePdfPath)) {
    $_FILES['invoice'] = [
        'name' => $invoicePdfFile,
        'tmp_name' => $invoicePdfPath,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($invoicePdfPath),
        'type' => 'application/pdf'
    ];
} else {
    // No PDF available - set empty upload
    $_FILES['invoice'] = [
        'name' => '',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
        'size' => 0,
        'type' => ''
    ];
}

// Set POST parameters
$_POST['shopName'] = $shopName;
$_POST['fileOption'] = 'manual';
$_POST['harmonized_mode'] = 'true';

// Set REQUEST_METHOD to POST
$_SERVER['REQUEST_METHOD'] = 'POST';

// Update session
$_SESSION['harmonizedFlow']['ocrSanityFile'] = $latestOcrSanityFile;
$_SESSION['harmonizedFlow']['priceListFile'] = $latestPriceListFile;
$_SESSION['harmonizedFlow']['step'] = 5;

// Include the existing Commercial Layer processor
// This will process the Commercial Layer and redirect to the appropriate verification screen
require_once __DIR__ . '/../commercialLayer/process_commercial_layer.php';
?>
