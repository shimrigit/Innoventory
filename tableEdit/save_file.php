<?php
require __DIR__ . '/../vendor/autoload.php';
include '../functions/configFunctions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

session_start();

// Read the incoming JSON data
$input = file_get_contents('php://input');

// Debugging: Print the raw input
//file_put_contents('debug.txt', "Raw Input:\n" . $input . "\n\n", FILE_APPEND);

// Decode the JSON data
$data = json_decode($input, true);

// Get the content to save
$content = $data['content'] ?? [];
$xlsxFile = $data['xlsxFile'] ?? 'data.xlsx';

// Construct the full path
//$relativePath = '../downloads/' . basename($xlsxFile);
//$fullPath = realpath($relativePath);

//we need to take the basic file and add the extention 
//echo "Original file name $xlsxFile <br>";
$newPathwithRV = replaceSuffix_RV($xlsxFile);
//echo "Original file with RV extention $fullPath <br>";

// Debugging: Print the constructed paths
//file_put_contents('debug.txt', "Constructed Paths in dave_file :\nNew  Path: $newPathwithRV\n", FILE_APPEND);

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($content as $rowIndex => $row) {
        foreach ($row as $cellIndex => $cellValue) {
            if ($cellValue !== '') { // Only save non-empty values
                $sheet->setCellValue([$cellIndex + 1, $rowIndex + 1], $cellValue);
            }
        }
    }

    //get the latest invoice sum 
    $invsum = $sheet->getcell("B4")->getValue();
    $_SESSION['invsum'] = $invsum;


    $outputFileName = $newPathwithRV; //$xlsxFile 
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($outputFileName);

    // Debugging: Confirm successful save
    //file_put_contents('debug.txt', "File saved successfully to: $outputFileName\n\n", FILE_APPEND);

    echo json_encode(['message' => "File '$outputFileName' has been saved successfully."]);
} catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
    //file_put_contents('debug.txt', "Error saving file: " . $e->getMessage() . "\n\n", FILE_APPEND);
    echo json_encode(['error' => 'Error saving file: ' . $e->getMessage()]);
}
?>
