<?php
/**
 * Step 4: Create OCR Sanity File (with Sequence Identifier)
 *
 * This step:
 * 1. Loads OCR JSON file from session
 * 2. Loads PDF file from session
 * 3. Calls the existing OCR Sanity creation process
 * 4. Generates OCRsanity file with sequence identifier in the filename
 * 5. Redirects to verification screen
 */

session_start();

// Check if harmonized flow session exists
if (!isset($_SESSION['harmonizedFlow'])) {
    die('Error: Harmonized flow session not found. Please start from Step 1.');
}

$flowData = $_SESSION['harmonizedFlow'];

// Get paths from session
$ocrJsonPath = $flowData['ocrJsonPath'];
$pdfPath = $flowData['pdfPath'];
$pdfFile = $flowData['pdfFile'];

// Verify files exist
if (!file_exists($ocrJsonPath)) {
    die('Error: OCR JSON file not found: ' . htmlspecialchars($ocrJsonPath));
}

if (!file_exists($pdfPath)) {
    die('Error: PDF file not found: ' . htmlspecialchars($pdfPath));
}

// Simulate file uploads for process_ocrsanity.php
// This allows us to use the existing process without modifying it extensively
$_FILES['ocr_json'] = [
    'name' => basename($ocrJsonPath),
    'tmp_name' => $ocrJsonPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($ocrJsonPath),
    'type' => 'application/json'
];

$_FILES['invoice_pdf'] = [
    'name' => $pdfFile,
    'tmp_name' => $pdfPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($pdfPath),
    'type' => 'application/pdf'
];

// Set harmonized mode flag so process_ocrsanity.php knows to redirect back to us
$_POST['harmonized_mode'] = 'true';
$_POST['harmonized_sequence'] = $flowData['sequenceIdentifier'];
$_POST['harmonized_supplier'] = $flowData['supplierName'];
$_POST['harmonized_date'] = $flowData['processDateFull'];

// Set REQUEST_METHOD to POST to trigger the processing
$_SERVER['REQUEST_METHOD'] = 'POST';

// Include the existing OCR Sanity processor
// This will create the OCRsanity file and redirect to verify_ocrsanity.php
require_once __DIR__ . '/../OCRsanity/process_ocrsanity.php';
?>
