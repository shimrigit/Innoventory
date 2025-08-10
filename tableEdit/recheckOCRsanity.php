<?php
require '../vendor/autoload.php';
include '../functions/configFunctions.php';
include '../functions/convertJsonToExcel.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
session_start();

//the file will handle the recheck if the OCR sanity after the ocrViewer and will send to a new viewer

// Debugging: Print the received parameters
//file_put_contents('debug.txt', "Received Parameters in recheckOCRsanity.php:\n" . print_r($_POST, true) . "\n\n", FILE_APPEND);

//get the original file name and add the extention to match the re-check convention 
$originalfileName = $_POST['file_name'] ?? 'NO FILE RECEIVED';

$newFileNameWithRV = replaceSuffix_RV($originalfileName);

// Your existing code to handle the recheck OCR sanity process
echo "Rechecking OCR sanity for file: " . htmlspecialchars($newFileNameWithRV, ENT_QUOTES, 'UTF-8')."<br>";

//load the file OCRsanity file to recheck and convert the spreadsheet 
$OCRSanitySpreadsheet = IOFactory::load($newFileNameWithRV);

//cleant the colors and borders of the Sanity file
//$OCRSanitySpreadsheet = cleanOCRsanityFile($OCRSanitySpreadsheet);

//extract the supplier name from the file
$supplierName = $OCRSanitySpreadsheet->getActiveSheet()->getCell('B2')->getvalue();

//upload supplier data
$supplierJsonData = uploadJSONdata('../suppliers.json');
$supplierData = getSupplierData($supplierJsonData, $supplierName);
//get shops json data
$shopsjsonData = uploadJSONdata('../shops.json');

//get the CheckSum method
$OCRcheckSumMethod =  $supplierData['OCRsanityMethod'];
echo "The OCRcheckSumMethod for $supplierName is $OCRcheckSumMethod<br>";

//echo for the sum sanity
echo "Start CheckSum <br>";


//initiate the sumPair
$sumPair = [0,0];

if ($OCRcheckSumMethod == 'Discount2') {
        //if has Discount2 use the checkSumOCRsanityDiscount2 function:
        //check for each line if LineTotal==((Price/(1+Discount1/100))/(1+Discount2/100))*Qty
        // AND if invocie total = calculated total
        $sumPair = checkSumOCRsanityDiscount2($OCRSanitySpreadsheet);
} elseif ($OCRcheckSumMethod == 'Discount1') {
        //if has Discount1 use the checkSumOCRsanityDiscount1 function:
        //check for each line if LineTotal==(Price/(1+Discount1/100))*Qty
        // AND if invocie total = calculated total
        $sumPair = checkSumOCRsanityDiscount1($OCRSanitySpreadsheet);
} elseif ($OCRcheckSumMethod == 'LineTotal') {
        //if has LineTotal use the checkSumOCRsanityLineTotal function:
        //check for each line  Linetotal=Price*Qty AND if invocie total = calculated total (sum of all LineTotals)
        $sumPair = checkSumOCRsanityLineTotal($OCRSanitySpreadsheet);
} elseif ($OCRcheckSumMethod == 'FlatDiscountAndSV') { 
        //if has FlatDiscountNexception use the checkSumOCRsanityNoLineTotal function
        //do a flat discount over the products which are not unders supersision
        $sumPair = checkSumOCRsanityNoLineTotal($OCRSanitySpreadsheet,$shopData);
} elseif ($OCRcheckSumMethod == 'Simple') {
        //else use the checkSumOCRsanitySimple function
        //check ONLY invocie total = calculated total
        $sumPair = checkSumOCRsanitySimple($OCRSanitySpreadsheet);
} else {
    //if the method not found by the suppiler name 
    echo "The CheckSum method for the supplier $supplierName was not found";
}

//check the sanity of the digits on the CatalogNo value
$OCRSanitySpreadsheet = checkBarcodeSanity($OCRSanitySpreadsheet);

//generate new suffix 
$OCRsanityRecheckfilePath = replaceSuffix_RV($newFileNameWithRV);

//save the Spreachsheet to Xlsx file with OCRsanity format 
$hrefdownloadPath = saveSpreadsheet($OCRSanitySpreadsheet, $OCRsanityRecheckfilePath,$rightToLeft=false,$suffix=''); //$OCRsanityRecheckfilePath

//build the name of the invocie pdf file
$ocdFilepdf = restoreInvoiceNamePdf($newFileNameWithRV);

//extracting the sums of the invice and calculation 
$invsum = $sumPair[0]; 
$calcsum = $sumPair[1];
//update the session parameters 
$_SESSION['invsum'] = $invsum;
$_SESSION['calcsum'] = $calcsum;

echo "PDF file name $ocdFilepdf";

//get URL from shopdata 
$localhostocrViewRawURL = $shopsjsonData['localhostocrViewURL'];
$hostAddress = $shopsjsonData['hostAddress'];

//insert the address to the url 
$localhostocrViewURL = str_replace("localhost", $hostAddress, $localhostocrViewRawURL);

//$IndexURL = $localhostocrViewURL."?invsum=".$invsum."&calcsum=".$calcsum."&pdf=../".$ocdFilePathpdf."&xlsx=../".$hrefdownloadPath;

$IndexURL = $localhostocrViewURL."?invsum=".$invsum."&calcsum=".$calcsum."&pdf=../ocrDir/".$ocdFilepdf."&xlsx=".$hrefdownloadPath;

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

//set a link to the screen to download the file
//echo '<a href="' . $hrefdownloadPath . '" download>Download rechecked OCRsanity file</a>';

?>


