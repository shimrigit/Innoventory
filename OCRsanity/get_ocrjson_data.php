<?php
/**
 * Get OCRjson Excel Data for Popup Display
 *
 * This file generates Excel data from the OCRjson file
 * and returns it as JSON for Handsontable display
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    // Get OCRjson filename from session
    $jsonFileName = null;

    // Check harmonized mode first
    if (isset($_SESSION['harmonizedFlow']['ocrJsonFile'])) {
        $jsonFileName = $_SESSION['harmonizedFlow']['ocrJsonFile'];
    }
    // Fall back to standard mode
    elseif (isset($_SESSION['ocrJsonFileName'])) {
        $jsonFileName = $_SESSION['ocrJsonFileName'];
    }

    if (!$jsonFileName) {
        throw new Exception("No OCRjson file found in session");
    }

    $jsonPath = __DIR__ . '/../AIocr/ocr_extracted/' . basename($jsonFileName);

    // Verify file exists
    if (!file_exists($jsonPath)) {
        throw new Exception("JSON file not found: " . htmlspecialchars($jsonFileName));
    }

    // Load and parse JSON
    $jsonContent = file_get_contents($jsonPath);
    $ocrData = json_decode($jsonContent, true);

    if ($ocrData === null) {
        throw new Exception("Failed to parse JSON file. Invalid JSON format.");
    }

    // Verify required fields
    if (!isset($ocrData['invoice_number']) || !isset($ocrData['invoice_date']) ||
        !isset($ocrData['invoice_total']) || !isset($ocrData['table'])) {
        throw new Exception("JSON file missing required fields");
    }

    // Build data array for Handsontable
    $excelData = [];

    // Row 1: Headers
    $headers = ['Invoice Param', 'Invoice Data'];

    // Get table column headers from first object
    $table = $ocrData['table'];
    if (!empty($table)) {
        $firstObject = $table[0];
        $columnHeaders = array_keys($firstObject);
        $headers = array_merge($headers, $columnHeaders);
    }

    $excelData[] = $headers;

    // Rows 2-4: Invoice metadata
    $excelData[] = ['invoice_number', $ocrData['invoice_number']];
    $excelData[] = ['invoice_date', $ocrData['invoice_date']];
    $excelData[] = ['invoice_total', $ocrData['invoice_total']];

    // Add table data rows
    foreach ($table as $rowData) {
        $row = ['', '']; // Empty first two columns
        foreach ($columnHeaders as $header) {
            $row[] = $rowData[$header] ?? '';
        }
        $excelData[] = $row;
    }

    // Calculate statistics
    $fieldCount = count($columnHeaders) - 1; // Exclude "index" field
    $rowCount = count($table);

    // Return data
    echo json_encode([
        'success' => true,
        'data' => $excelData,
        'fileName' => $jsonFileName,
        'fieldCount' => $fieldCount,
        'rowCount' => $rowCount,
        'colHeaders' => $headers
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
