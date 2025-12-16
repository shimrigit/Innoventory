<?php
/**
 * Process PDF Selection for Existing Sanity File
 * Handles the PDF selection from select_pdf_for_sanity.php
 */

session_start();

// Check if harmonized flow session exists
if (!isset($_SESSION['harmonizedFlow'])) {
    die('Error: Harmonized flow session not found. Please start from Step 1.');
}

$flowData = $_SESSION['harmonizedFlow'];
$sanityFile = $flowData['sanityFileName'] ?? 'Unknown';
$shopName = $flowData['shopName'] ?? 'Unknown';

// Get the submit action
$submitAction = $_POST['submitAction'] ?? null;

if (!$submitAction) {
    die('Error: No action specified');
}

// Handle Skip PDF action
if ($submitAction === 'skip') {
    // Redirect to verify_ocrsanity.php without PDF
    header('Location: ../OCRsanity/verify_ocrsanity.php?excel=' . urlencode($sanityFile) .
           '&pdf=NOT_FOUND.pdf' .
           '&shop=' . urlencode($shopName) .
           '&harmonized=true');
    exit;
}

// Handle Continue with PDF action
if ($submitAction === 'continue') {
    $pdfFile = $_POST['pdfFile'] ?? null;

    if (!$pdfFile) {
        die('Error: No PDF file selected');
    }

    // Verify PDF exists
    if (!file_exists($pdfFile)) {
        die('Error: Selected PDF file not found: ' . htmlspecialchars($pdfFile));
    }

    // Get just the filename
    $pdfFileName = basename($pdfFile);

    // Copy PDF to sanity_files directory if it's not already there
    $sanityFilesDir = __DIR__ . '/../OCRsanity/sanity_files/';
    $targetPdfPath = $sanityFilesDir . $pdfFileName;

    // If PDF is not already in sanity_files directory, copy it there
    if (realpath($pdfFile) !== realpath($targetPdfPath)) {
        if (!copy($pdfFile, $targetPdfPath)) {
            die('Error: Failed to copy PDF to sanity_files directory');
        }
    }

    // Store PDF info in session
    $_SESSION['harmonizedFlow']['pdfFile'] = $pdfFileName;
    $_SESSION['harmonizedFlow']['pdfPath'] = $targetPdfPath;

    // Redirect to verify_ocrsanity.php with both excel and pdf parameters
    header('Location: ../OCRsanity/verify_ocrsanity.php?excel=' . urlencode($sanityFile) .
           '&pdf=' . urlencode($pdfFileName) .
           '&shop=' . urlencode($shopName) .
           '&harmonized=true');
    exit;
}

die('Error: Invalid action: ' . htmlspecialchars($submitAction));
