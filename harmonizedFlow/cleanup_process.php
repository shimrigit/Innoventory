<?php
/**
 * Cleanup Process - Move Files to Uploads Directory
 *
 * This script moves PDF and OCRjson files to the uploads directory
 * after the harmonized flow is complete.
 */

session_start();
header('Content-Type: application/json');

try {
    // Check if harmonized flow session exists
    if (!isset($_SESSION['harmonizedFlow'])) {
        throw new Exception("No harmonized flow session found");
    }

    $flowData = $_SESSION['harmonizedFlow'];
    $movedFiles = [];
    $errors = [];

    // Define directories
    $uploadsDir = __DIR__ . '/../uploads/';
    $preProcessDir = __DIR__ . '/../preProcessDir/';
    $ocrExtractedDir = __DIR__ . '/../AIocr/ocr_extracted/';

    // Ensure uploads directory exists
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0777, true);
    }

    // 1. Move PDF file from preProcessDir to uploads
    if (isset($flowData['pdfFile'])) {
        $pdfFile = $flowData['pdfFile'];
        $sourcePath = $preProcessDir . $pdfFile;
        $destPath = $uploadsDir . $pdfFile;

        if (file_exists($sourcePath)) {
            if (rename($sourcePath, $destPath)) {
                $movedFiles['pdf'] = [
                    'fileName' => $pdfFile,
                    'from' => 'preProcessDir/',
                    'to' => 'uploads/'
                ];
            } else {
                $errors[] = "Failed to move PDF file: $pdfFile";
            }
        } else {
            $errors[] = "PDF file not found: $pdfFile";
        }
    }

    // 2. Move OCRjson file from AIocr/ocr_extracted to uploads
    if (isset($flowData['ocrJsonFile'])) {
        $ocrJsonFile = $flowData['ocrJsonFile'];
        $sourcePath = $ocrExtractedDir . $ocrJsonFile;
        $destPath = $uploadsDir . $ocrJsonFile;

        if (file_exists($sourcePath)) {
            if (rename($sourcePath, $destPath)) {
                $movedFiles['ocrJson'] = [
                    'fileName' => $ocrJsonFile,
                    'from' => 'AIocr/ocr_extracted/',
                    'to' => 'uploads/'
                ];
            } else {
                $errors[] = "Failed to move OCRjson file: $ocrJsonFile";
            }
        } else {
            $errors[] = "OCRjson file not found: $ocrJsonFile";
        }
    }

    // 3. Get CL, PC, NP file information (they stay in commercialLayer directory)
    $clFile = $_GET['clFile'] ?? null;
    $pcFile = $_GET['pcFile'] ?? null;
    $npFile = $_GET['npFile'] ?? null;

    $stayingFiles = [];

    if ($clFile) {
        $stayingFiles['cl'] = [
            'fileName' => $clFile,
            'location' => 'commercialLayer/commercial_invoice_files/'
        ];
    }

    if ($pcFile) {
        $stayingFiles['pc'] = [
            'fileName' => $pcFile,
            'location' => 'commercialLayer/commercial_invoice_files/'
        ];
    }

    if ($npFile) {
        $stayingFiles['np'] = [
            'fileName' => $npFile,
            'location' => 'commercialLayer/commercial_invoice_files/'
        ];
    }

    // Return results
    echo json_encode([
        'success' => true,
        'movedFiles' => $movedFiles,
        'stayingFiles' => $stayingFiles,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
