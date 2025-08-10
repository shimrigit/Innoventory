<?php

require '../vendor/autoload.php';
include '../functions/configFunctions.php';
include '../functions/convertJsonToExcel.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
session_start();

// Retrieve content and file name from POST
$content = isset($_POST['content']) ? json_decode($_POST['content'], true) : null;
$originalFile = isset($_POST['xlsxFile']) ? $_POST['xlsxFile'] : null;
$pdfFile = isset($_POST['pdfFile']) ? $_POST['pdfFile'] : null;

// Log the received content for debugging
//file_put_contents(__DIR__ . '/boViewer_log.txt', "[" . date("Y-m-d H:i:s") . "] Received Content:\n" . print_r($content, true)."\n", FILE_APPEND);
//file_put_contents(__DIR__ . '/boViewer_log.txt', "[" . date("Y-m-d H:i:s") . "] Xlsx file name:\n" . print_r($originalFile, true)."\n", FILE_APPEND);
//file_put_contents(__DIR__ . '/boViewer_log.txt', "[" . date("Y-m-d H:i:s") . "] PDF file name:\n" . print_r($pdfFile, true)."\n", FILE_APPEND);

echo "Start to check the new data with the price list <br>";

//get the supplier name and shop data 
$supplier = $_SESSION['supplier'];
$shop = $_SESSION['shop'];
//upload shop  data and supplier data
$shopsjsonData = uploadJSONdata('../shops.json');
$shopData = getShopForm($shopsjsonData,$shop);
$supplierJsonData = uploadJSONdata('../suppliers.json');
$supplierData = getSupplierData($supplierJsonData, $supplier);

// Convert the content to Spreadsheet
$boRevisedSpreadsheet = new Spreadsheet();
$boRevised = $boRevisedSpreadsheet->getActiveSheet();

// Populate the spreadsheet with the JSON array content using coordinateFromIndices
foreach ($content as $rowIndex => $row) {
    foreach ($row as $colIndex => $cell) {
        $value = $cell['value'];
        $coordinate = coordinateFromIndices($colIndex, $rowIndex); // Get Excel-style coordinate
        $boRevised->setCellValue($coordinate, $value); // Use the coordinate to set the value
    }
}

//prepare the parameters for the pricelist check 

//get OCR dir path
$ocrDirLocation = $shopsjsonData['ocr_dir_relative_path']; 
//get the pricelist path
$PriceListfileName = getNewestXlsxFileName('../'.$ocrDirLocation);
$PriceListfilePath = "../".$ocrDirLocation."/".$PriceListfileName;

//run the pricelist analyze over the revised spreadsheet 
$boSpreadsheetAfterPriceListcheck = processPriceListcheck($boRevisedSpreadsheet,$shopData,$PriceListfilePath);

// Add suffix _RV0 to the file base name and save it in the same directory
$fileParts = pathinfo($originalFile);
$directory = $fileParts['dirname'];
$baseName = $fileParts['filename'];
$extension = $fileParts['extension'];

//$newFileName = $directory . '/' . $baseName . '_RV0.' . $extension;

// Check if the original file name ends with "_RVx" (x is a natural number)
if (preg_match('/_RV(\d+)$/', $baseName, $matches)) {
    // Extract the current value of x and increment it
    $currentRV = intval($matches[1]);
    $newRV = $currentRV + 1;
    // Replace the old "_RVx" suffix with "_RV(newRV)"
    $newBaseName = preg_replace('/_RV\d+$/', '_RV' . $newRV, $baseName);
} else {
    // If no "_RVx" suffix, add "_RV0"
    $newBaseName = $baseName . '_RV0';
}

// Construct the new file name
$newFileName = $directory . '/' . $newBaseName . '.' . $extension;

// Save the revised spreadsheet
$writer = IOFactory::createWriter($boSpreadsheetAfterPriceListcheck , 'Xlsx');
$writer->save($newFileName);

// Log the saved file path for debugging
file_put_contents(__DIR__ . '/boViewer_log.txt', "[" . date("Y-m-d H:i:s") . "] Saved Revised File:\n" . $newFileName . "\n", FILE_APPEND);



//get URL from shopdata 
$localhostboViewRawURL = $shopsjsonData['localhostboViewURL'];
$hostAddress = $shopsjsonData['hostAddress'];

//insert the address to the url 
$localhostboViewURL = str_replace("localhost", $hostAddress, $localhostboViewRawURL);

$IndexURL = $localhostboViewURL."?pdf=../ocrDir/".$pdfFile."&xlsx=".$newFileName ;

echo '<script type="text/javascript">
window.open("' . $IndexURL . '", "_blank");
</script>';

?>
