<?php
/**
 * AI Enhancer - Save Crops Handler
 *
 * Receives cropped images from multi_rectangle_crop_viewer
 * Saves them to AIE/crops/ directory
 * Then processes with OpenAI
 */

session_start();

header('Content-Type: application/json');

// Check if this is an AIE session
if (!isset($_SESSION['aie_mode']) || $_SESSION['aie_mode'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Invalid session - not in AIE mode']);
    exit;
}

// Get POST data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['crops']) || empty($data['crops'])) {
    echo json_encode(['success' => false, 'error' => 'No crop data received']);
    exit;
}

$crops = $data['crops'];
$pdfFile = $_SESSION['aie_pdf_file'] ?? 'unknown';

// Create crops directory if it doesn't exist
$cropsDir = __DIR__ . '/crops/';
if (!file_exists($cropsDir)) {
    mkdir($cropsDir, 0755, true);
}

// Generate base name from PDF file (remove .pdf extension)
// Preserve the PDF naming convention: XXXX_dd-mm-yy_Z or AIE_ddmmyyyy_hhmmss
$baseName = pathinfo($pdfFile, PATHINFO_FILENAME);

// Store base name in session for later use in OCRjson naming
$_SESSION['aie_pdf_basename'] = $baseName;

// Expected crop labels mapping
$cropLabels = [
    'invoice_no' => 'InvoiceNo',
    'date' => 'Date',
    'table1' => 'Table1',
    'table2' => 'Table2',
    'table3' => 'Table3',
    'table4' => 'Table4',
    'table5' => 'Table5',
    'total' => 'Total'
];

// Save each crop as PNG
$savedCrops = [];
$cropFilesPaths = [];

foreach ($crops as $crop) {
    $label = $crop['label'] ?? '';
    $imageData = $crop['image'] ?? '';

    if (empty($label) || empty($imageData)) {
        continue;
    }

    // Convert label to standard format
    $labelLower = strtolower(str_replace(' ', '_', $label));

    // Determine suffix for filename
    $suffix = $cropLabels[$labelLower] ?? ucfirst($labelLower);

    // Remove data:image/png;base64, prefix
    $imageData = preg_replace('/^data:image\/png;base64,/', '', $imageData);

    // Decode base64
    $decodedImage = base64_decode($imageData);

    if ($decodedImage === false) {
        continue;
    }

    // Create filename with versioning if file already exists
    $baseFilename = $baseName . '_' . $suffix;
    $filename = $baseFilename . '.png';
    $filepath = $cropsDir . $filename;

    // If file exists, add (1), (2), (3), etc. suffix
    if (file_exists($filepath)) {
        $version = 1;
        while (file_exists($cropsDir . $baseFilename . ' (' . $version . ').png')) {
            $version++;
        }
        $filename = $baseFilename . ' (' . $version . ').png';
        $filepath = $cropsDir . $filename;
    }

    // Save file
    if (file_put_contents($filepath, $decodedImage) !== false) {
        $savedCrops[] = $filename;
        $cropFilesPaths[] = $filepath;
    }
}

if (empty($savedCrops)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save any crops']);
    exit;
}

// Store crop files in session for OpenAI processing
$_SESSION['aie_crop_files'] = $cropFilesPaths;
$_SESSION['aie_crop_names'] = $savedCrops;

echo json_encode([
    'success' => true,
    'saved_crops' => $savedCrops,
    'crop_count' => count($savedCrops),
    'next_step' => 'process_with_openai_aie.php'
]);
exit;
