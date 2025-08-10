<?php
require '../vendor/autoload.php'; // Ensure MPDF is autoloaded

use Mpdf\Mpdf;

session_start(); // Start session

// Set directory and log file
//$directory = 'C:\\xampp\\htdocs\\website\\preProcessDir';
//$logFile = $directory . '\\filelog.txt';

$directory = $_SESSION['preProcessDirectory'];
$logFile = $_SESSION['logFile'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fileName = $_POST['relativeUrl'];    // Relative URL for display
    $absolutePath = $_POST['absolutePath'];  // Absolute path for unlink()
    $supplierName = $_POST['supplierName'];
    $selectedDate = date('d-m-y', strtotime($_POST['selectedDate']));
    $action = $_POST['action'];

    $fileBaseName = basename($fileName);

    if ($action == 'save') {
        // Convert to PDF
        $newFileName = $supplierName . ' ' . $selectedDate . '.pdf';
        $newFilePath = dirname($absolutePath) . '\\' . $newFileName;
        $n = 1;

        // Check for existing files with the same name
        while (file_exists($newFilePath)) {
            $newFileName = $supplierName . ' ' . $selectedDate . ' ' . $n++ . '.pdf';
            $newFilePath = dirname($absolutePath) . '\\' . $newFileName;
        }

        // ---- TEMP DIRECTORY SETUP ----
        $tempDir = 'C:\\xampp\\htdocs\\website\\tmp\\mpdf';
        if (!is_writable($tempDir)) {
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mpdf';
        }

        // Ensure the directory exists
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        // ---- END TEMP DIRECTORY SETUP ----

        try {
            // Convert JPG to PDF using MPDF with temp directory
            $mpdf = new Mpdf([
                'tempDir' => $tempDir
            ]);

            $html = '<img src="' . $fileName . '" style="width: 100%; height: auto;">';
            $mpdf->WriteHTML($html);
            $mpdf->Output($newFilePath, \Mpdf\Output\Destination::FILE);

            // Log the save action
            $logMessage = "File $fileBaseName was saved as file $newFileName<br>";
            echo $logMessage;
            file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

        } catch (\Mpdf\MpdfException $e) {
            $logMessage = "Error creating PDF: " . $e->getMessage() . "<br>";
            echo $logMessage;
            file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
        }
    } elseif ($action == 'discard') {
        // Log the discard action
        $logMessage =  "File $fileBaseName was discarded<br>";
        echo $logMessage;
        file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND); 
    }

    // Remove the processed file using the absolute path
    if (file_exists($absolutePath)) {
        unlink($absolutePath);
    } else {
        $logMessage =  "Error: File $absolutePath does not exist.<br>";
        echo $logMessage;
        file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND); 
    }
}
?>
