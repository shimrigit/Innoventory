<?php

//test to try and create a new mpdf file
require 'vendor/autoload.php';  // Load MPDF library

use Mpdf\Mpdf;

try {
    // Define the file path
    $filePath = 'C:\\xampp\\htdocs\\website\\preProcessDir\\hello_world.pdf';

    // Specify a writable temporary directory with the 'mpdf' subfolder
    $tempDir = 'C:\\xampp\\htdocs\\website\\tmp\\mpdf';

    // Ensure the directory exists
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    // Create a new MPDF instance with the custom temp directory
    $mpdf = new Mpdf([
        'tempDir' => $tempDir  // Set correct temp directory
    ]);

    // Write some content to the PDF
    $mpdf->WriteHTML('<h1>Hello World</h1>');

    // Save the PDF to the specified directory
    $mpdf->Output($filePath, \Mpdf\Output\Destination::FILE);

    echo "PDF successfully created at: " . $filePath;
} catch (\Mpdf\MpdfException $e) {
    echo "An error occurred while generating the PDF: " . $e->getMessage();
}


//test
/*
$dir = 'C:/xampp/htdocs/website/toBackOffice'; //C:\xampp\htdocs\website\toBackOffice // 'C:/xampp/htdocs/website/vendor/mpdf/mpdf/tmp'
if (is_writable($dir)) {
    echo "The folder is writable!";
} else {
    echo "The folder is NOT writable!";
}
    */

?>
