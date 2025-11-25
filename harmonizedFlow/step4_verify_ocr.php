<?php
/**
 * Step 4: Create OCRsanity File and Verify (with Auto-file Detection)
 *
 * This step:
 * 1. Automatically finds the latest OCRjson file from ocr_extracted directory
 * 2. Gets the PDF from session
 * 3. Calls process_ocrsanity.php to create OCRsanity Excel file
 * 4. Redirects to verify_ocrsanity.php
 */

session_start();

// Check if harmonized flow session exists
if (!isset($_SESSION['harmonizedFlow'])) {
    die('Error: Harmonized flow session not found. Please start from Step 1.');
}

$flowData = $_SESSION['harmonizedFlow'];

// Get the latest OCRjson file from ocr_extracted directory
$ocrExtractedDir = __DIR__ . '/../AIocr/ocr_extracted/';
$allJsonFiles = glob($ocrExtractedDir . 'OCRjson*.json');

if (empty($allJsonFiles)) {
    die('Error: No OCRjson files found in ocr_extracted directory.');
}

// Sort by modification time (newest first)
usort($allJsonFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Get the most recent OCRjson file path
$latestOcrJsonPath = $allJsonFiles[0];
$latestOcrJsonFile = basename($latestOcrJsonPath);

// Get PDF path from session
$pdfPath = $flowData['pdfPath'];
$pdfFile = $flowData['pdfFile'];

// Verify files exist
if (!file_exists($latestOcrJsonPath)) {
    die('Error: OCRjson file not found: ' . htmlspecialchars($latestOcrJsonPath));
}

if (!file_exists($pdfPath)) {
    die('Error: PDF file not found: ' . htmlspecialchars($pdfPath));
}

// Simulate file uploads for process_ocrsanity.php
$_FILES['ocr_json'] = [
    'name' => $latestOcrJsonFile,
    'tmp_name' => $latestOcrJsonPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($latestOcrJsonPath),
    'type' => 'application/json'
];

$_FILES['invoice_pdf'] = [
    'name' => $pdfFile,
    'tmp_name' => $pdfPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($pdfPath),
    'type' => 'application/pdf'
];

// Set harmonized mode flags
$_POST['harmonized_mode'] = 'true';
$_POST['harmonized_sequence'] = $flowData['sequenceIdentifier'];
$_POST['harmonized_supplier'] = $flowData['supplierName'];
$_POST['harmonized_date'] = $flowData['processDateFull'];

// Set REQUEST_METHOD to POST
$_SERVER['REQUEST_METHOD'] = 'POST';

// Update session
$_SESSION['harmonizedFlow']['ocrJsonFile'] = $latestOcrJsonFile;
$_SESSION['harmonizedFlow']['step'] = 4;

// Include the existing OCR Sanity processor
// This will create the OCRsanity Excel file and redirect to verify_ocrsanity.php
require_once __DIR__ . '/../OCRsanity/process_ocrsanity.php';
?>
