<?php

//process_append.php 

session_start(); // Start session

require_once '../vendor/autoload.php'; // Load FPDI

use setasign\Fpdi\Fpdi;

$directory = $_SESSION['preProcessDirectory'];
$logFile = $_SESSION['logFile'];


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $files = $_POST['file'];
    $pageNumbers = $_POST['pageNumber'];
    $subGroups = $_POST['subGroup'];
    $groupBaseName = ''; // This will hold the base name for logging

    // Group page numbers by sub-group
    $subGroupPageNumbers = [];
    foreach ($files as $index => $file) {
        $subGroup = $subGroups[$index];
        $pageNumber = $pageNumbers[$index];
        // Add the page number to the respective sub-group
        $subGroupPageNumbers[$subGroup][] = $pageNumber;
    }

    // Check for duplicate page numbers within each sub-group
    foreach ($subGroupPageNumbers as $subGroup => $pageNumbers) {
        $pageNumberCount = array_count_values($pageNumbers); // Count occurrences of each page number
        foreach ($pageNumberCount as $pageNumber => $count) {
            if ($count > 1) {
                $_SESSION['error'] = "Error: In subgroup $subGroup, page number $pageNumber has been assigned to multiple files. Please assign unique page numbers within each subgroup.";
                header('Location: append_pages.php');
                exit;
            }
        }
    }

    // Process the files based on sub-groups and page numbers
    $groupedFiles = [];

    foreach ($files as $index => $file) {
        $subGroup = $subGroups[$index];
        $pageNumber = $pageNumbers[$index];

        // Add file to subgroup
        $groupedFiles[$subGroup][$pageNumber] = $file;

        // Set base name (from the first file)
        if (empty($groupBaseName)) {
            $groupBaseName = preg_replace('/\s\d+$/', '', basename($file, '.pdf'));
        }
    }

    // Process each sub-group
    foreach ($groupedFiles as $subGroup => $pages) {
        ksort($pages); // Sort by page number

        // Create an FPDI object for merging PDFs
        $pdf = new Fpdi();

        // Merge the PDF files by importing each file page-by-page
        foreach ($pages as $file) {
            $pageCount = $pdf->setSourceFile($file); // Set the source file

            // Import all pages from the source file into FPDI
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $pdf->importPage($pageNo); // Import page
                $pdf->AddPage(); // Add a new page to the output PDF
                $pdf->useTemplate($tplId); // Use the imported page template
            }
        }

        // Output file name: "xxxx dd-mm-yy Z.pdf"
        $outputFileName = "{$groupBaseName} {$subGroup}.pdf";
        $outputFile = dirname($file) . '/' . $outputFileName; // Save in the same directory

        // Save the merged PDF to the same directory
        $pdf->Output($outputFile, 'F'); // 'F' saves to a file instead of outputting it directly

        // Log the process to file
        $logMessage = "Invoice {$groupBaseName} {$subGroup} was appended ";
        echo $logMessage."<br>";  
        file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND); 
        //file_put_contents('logfile.txt', "Invoice {$groupBaseName} {$subGroup} was appended\n", FILE_APPEND);

        // Delete original files
        foreach ($pages as $file) {
            unlink($file); // Remove original files
        }

        // Output success message
        $logMessage = "Process completed for subgroup {$subGroup} ";
        echo $logMessage."<br>";  
        file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND); 
    }

    // Remove the processed group from the session
    array_shift($_SESSION['groups']);  // Remove the first group as it's been processed

    // Redirect back to append_pages.php to process the next group
    header('Location: append_pages.php');
    exit;
}
