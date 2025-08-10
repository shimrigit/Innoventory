<?php

require '../vendor/autoload.php';
include '../functions/configFunctions.php';
include '../functions/convertJsonToExcel.php';
include '../emailMessages/generateEmailMessage.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
session_start();

// Debugging: Print the received parameters
//file_put_contents('ocrViewer_log.txt', "Received Parameters in compareToItemsPriceList.php:\n" . print_r($_POST, true) . "\n\n", FILE_APPEND);

$originalfilePath = $_POST['file_name'] ?? '';

$newFilePathWithRV = replaceSuffix_RV($originalfilePath);

// Your existing code to handle the conversion to back-office sheet process
echo "Converting to back-office sheet for file: " . htmlspecialchars($newFilePathWithRV, ENT_QUOTES, 'UTF-8')."<br>";

//access the session parameters 
if (isset($_SESSION['shop']) && isset($_SESSION['supplier'])) {
    $shop = $_SESSION['shop'];
    //$supplier = $_SESSION['supplier'];
    //echo "shop: " . $_SESSION['shop'] . "<br>";
    //echo "supplier: " . $_SESSION['supplier']. "<br>";
} else {
    echo "Session variables are not set.";
}

//get the supplier name from the file name
$supplier = getSupplierNameFromFilePath($originalfilePath);
//get the date value of the invoice from the file path 

//echo "supplier: $supplier <br>";
$_SESSION['supplier'] = $supplier;


//upload shop  data and supplier data
$shopsjsonData = uploadJSONdata('../shops.json');
$shopData = getShopForm($shopsjsonData,$shop);
$supplierJsonData = uploadJSONdata('../suppliers.json');
$supplierData = getSupplierData($supplierJsonData, $supplier);
//get OCR dir path
$ocrDirLocation = $shopsjsonData['ocr_dir_relative_path']; 
$reviewBOSheet = $shopsjsonData['review_backoffice_sheet']; 

//load the file OCRsanity file  and convert the spreadsheet 
$OCRSanitySpreadsheet = IOFactory::load($newFilePathWithRV);

//get the OCRsanity info of invoice Date and invoice number into session info
$_SESSION['invoiceDate'] = $OCRSanitySpreadsheet->getActiveSheet()->getCell([2,3])->getvalue();
$_SESSION['invoiceNumber'] = $OCRSanitySpreadsheet->getActiveSheet()->getCell([2,1])->getvalue();
$invoiceDate = $_SESSION['invoiceDate'];
$invoiceNumber = $_SESSION['invoiceNumber'];
//debug
echo "invoiceDate $invoiceDate <br>";
echo "invoiceNumber $invoiceNumber <br>";

//get the pricelist path
$PriceListfileName = getNewestXlsxFileName('../'.$ocrDirLocation);
$PriceListfilePath = "../".$ocrDirLocation."/".$PriceListfileName;

//generate Pricelist basic format 
$itemsBasicSpreadsheet = convertToPriceListBasicSheet($OCRSanitySpreadsheet,$shopData);

//run the pricelist analyze over the basic format 
$itemsAfterPriceListcheck = processPriceListcheck($itemsBasicSpreadsheet,$shopData,$PriceListfilePath);

//Save the pricelist analyzed file 
$hrefdownloadPathItemsSheet = saveSpreadsheetWithTimestamp($itemsAfterPriceListcheck, $newFilePathWithRV,$rightToLeft=true,$suffix='_PL');

//Send the sheet after price list check for the user view 
//get URL from shopdata 
$localhostboViewRawURL = $shopsjsonData['localhostboViewURL'];
$hostAddress = $shopsjsonData['hostAddress'];

//insert the address to the url 
$localhostboViewURL = str_replace("localhost", $hostAddress, $localhostboViewRawURL);

$invoiceFilepdf = invoiceFileNametoBOpdf($hrefdownloadPathItemsSheet);

$IndexURL = $localhostboViewURL."?pdf=../ocrDir/".$invoiceFilepdf."&xlsx=".$hrefdownloadPathItemsSheet;

// JavaScript to open the URL in a new window
echo '<script type="text/javascript">
window.open("' . $IndexURL . '", "_blank");
</script>';

// Add a small delay to ensure the new window is opened before showing the buttons
echo '<script type="text/javascript">
setTimeout(function() {
    document.getElementById("choiceForm").style.display = "block";
}, 5000); // Adjust the timeout if necessary
</script>';



?>

