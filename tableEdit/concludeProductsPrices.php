<?php
require '../vendor/autoload.php';
include '../functions/configFunctions.php';
include '../functions/priceChangeFunctions.php';
include '../functions/convertJsonToExcel.php';
include '../emailMessages/generateEmailMessage.php';
require '../gmailAccess/gmail_functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
session_start();

//get the original file name and add the extention to match the re-check convention 
$originalfileName = $_POST['file_name'] ?? 'NO FILE RECEIVED';
echo "File to upload: $originalfileName <br>";

//get the supplier name and shop data 
$supplier = $_SESSION['supplier'];
$shop = $_SESSION['shop'];
//debug
echo "shop: " . $_SESSION['shop'] . "<br>";
echo "supplier: " . $_SESSION['supplier']. "<br>";

//upload shop data and supplier data
$shopsjsonData = uploadJSONdata('../shops.json');
$shopData = getShopForm($shopsjsonData,$shop);
$supplierJsonData = uploadJSONdata('../suppliers.json');
$supplierData = getSupplierData($supplierJsonData, $supplier);

//load the backoffice file as a spreadSheet 
$itemsListSpreadsheet = IOFactory::load($originalfileName);

//run the new product analyzing and create a list of new productrs. 
//************************************************************

//"NOT FOUND" column is the "Diff" column of the spreadsheet
//get the attributesColumns value from the shop form
$attributesColumnsVal = $shopData["attributesColumns"];
// Extract the "Diff" column e.g. "NOT FOUND" column from the attributesColumns array
$NOT_FOUND_column = null;
foreach ($attributesColumnsVal as $attribute) {
    if (isset($attribute['Diff'])) {
        $NOT_FOUND_column = $attribute['Diff'];
        break; // Exit the loop once "Price" is found
    }
}
//echo "NOT FOUND (Diff) column is $NOT_FOUND_column <br>"; 

$newProductsList = creatListOfNewProducts($itemsListSpreadsheet,$NOT_FOUND_column,$shopData,$supplier,$originalfileName);

//change the data type
$dataTypeArray = $shopData["dataTypeColumns"];
//change the spreadsheet datatype and save the file again 
restoreSpreadSheetDataType($itemsListSpreadsheet,$dataTypeArray,$originalfileName);

//add VAT, weighable and sale price  - VWS
//get the array for parameters 


//set the link to the $itemsListSpreadsheet
echo '<a href="' . $originalfileName . '" download>Download items list post-processed file</a><br>';

$hrefdownloadNewProductrs = null;

//if the list is empty - echo . else save and set the link to the screen 
if ($newProductsList!=null) {
    //save the new products list 
    $hrefdownloadNewProductrs = saveSpreadsheetWithTimestamp($newProductsList, $originalfileName,$rightToLeft=true,$suffix='_NEW-PRODUCTS');

    //set a link to the screen to download the New products list file 
    echo '<a href="' . $hrefdownloadNewProductrs . '" download>Download new products file</a><br>';


} else {
    echo "No new products list was generated <br>";
}


//check for price change and create price change list 
//******************************************** 

//get the processPriceChange controler
$PriceChangeArray = $shopData['processPriceChange'];
//$costPriceIndicator = $PriceChangeArray[0];
$salePriceIndicator = $PriceChangeArray[1];
$diffThreshold = $PriceChangeArray[2];
//get the Back office  column value 
$columnsArray = $shopData['attributesColumns'];
$diffColumn = $originalCostColumn = $newCostColumn = $originalSalePriceColumn = null; 
foreach ($columnsArray as $item) {
    if (array_key_exists("Diff", $item)) {
        $diffColumn = $item["Diff"];
    } elseif (array_key_exists("Price", $item)) {
        $priceColumn = $item["Price"];
    } elseif (array_key_exists("OriginalPrice", $item)) {
        $originalPriceColumn = $item["OriginalPrice"];
    } elseif (array_key_exists("SalePrice", $item)) {
        $originalSalePriceColumn = $item["SalePrice"];
    } elseif (array_key_exists("Department", $item)) {
        $originalDepartmentColumn = $item["Department"];
    }
}
//break if there the column attributes are not set  
if ($diffColumn == null or $priceColumn == null or $originalPriceColumn == null or $originalSalePriceColumn == null or $originalDepartmentColumn == null) {
    die("The column values for price change are not set in shops file attributesColumns attribute - process will terminate");
}

//if $salePriceIndicator is 1 process the price change list 
$priceChangeList = null;
if ($salePriceIndicator == 1) {
    //create a list of items with price list . if no price changes then return null
    $priceChangeList = filterPriceChanges($itemsListSpreadsheet, $diffColumn, $diffThreshold);

    //if there are price changes and the request for cost  change excel is on
    if ($priceChangeList !=null) {

        //create a sheet with the right format for cost price change 
        $priceChangeHeaderArray = $shopData['priceChangeHeader'];
        $priceChangeMappingArray = $shopData['priceChangeMapping'];
        $departmentsConfigFileLocation = $shopData['departmentsConfigFile'];
        $supervisedProductsFile = $shopData['supervisedProductsFile'];
        $priceChangeIndicatorsArray = $shopData['priceChangeIndicators'];
        $saleNewPriceColumn = $priceChangeIndicatorsArray[0];
        $originalMarginColumn = $priceChangeIndicatorsArray[1];
        $VAT_multiplexer = $priceChangeIndicatorsArray[2];
        $recommendedMarginColumn = $priceChangeIndicatorsArray[3];
        $possibleMarginColumn = $priceChangeIndicatorsArray[4];
        $priceChangeIndicatorColumn = $priceChangeIndicatorsArray[5]; 
        $invoiceIdintifierColumn = $priceChangeIndicatorsArray[6]; 
        //$priceChangeIndicatorsArray[7] - not needed here
        $priceChangeThresholdPercentage = $priceChangeIndicatorsArray[8];
        $barcodeColumn = $priceChangeIndicatorsArray[9];
        $maxExtraMargin = $priceChangeIndicatorsArray[10];



        //map the price values to the price change format
        $priceChangeSheet = mapOriginalSheetToNewSheet($priceChangeList, $priceChangeHeaderArray, $priceChangeMappingArray);
        //upload department config file 
        $departmentConfigObject = uploadJSONdata($departmentsConfigFileLocation);
        //generate recommneded margin array 
        $recommendedMarginArry = generateRecommendedMarginArray($priceChangeList,$departmentConfigObject,$originalDepartmentColumn);
        //add recommneded margins to the $priceChangeSheet
        $priceChangeSheet = updateColumn($priceChangeSheet, $recommendedMarginArry, $recommendedMarginColumn);

        //upload the supervision JSON
        $supervisedJSON  = uploadJSONdata($supervisedProductsFile);

        //generate invoice identifier array 
        $invoiceIdentifier = generateInvoiceIdentifierFromFileName($originalfileName);
        //insert the identifier to the invoice 
        insertValueToColumnFrom2($invoiceIdentifier, $invoiceIdintifierColumn, $priceChangeSheet);
    
        //generate new sale price and indicators array return[0] and margine array return[1]
        //echo "priceChangeThresholdPercentage $priceChangeThresholdPercentage <br>";
        $newSalePriceIndicatorsArry = SalePriceChangeGenerator($priceChangeList, $originalPriceColumn, $priceColumn, $originalSalePriceColumn,$recommendedMarginArry, 
                                    $VAT_multiplexer,$priceChangeThresholdPercentage,$barcodeColumn,$supervisedJSON,$maxExtraMargin); 
        $newSalePriceArry = $newSalePriceIndicatorsArry[0];
        $originalMarginArray = $newSalePriceIndicatorsArry[1];
        $possibleMarginArray = $newSalePriceIndicatorsArry[2];
        $priceChangeIndicatorArray = $newSalePriceIndicatorsArry[3];

        //add the new prices to the sheet  
        $priceChangeSheet = updateColumn($priceChangeSheet, $newSalePriceArry, $saleNewPriceColumn);
        //add the original margins to the sheet  
        $priceChangeSheet = updateColumn($priceChangeSheet, $originalMarginArray, $originalMarginColumn);
        //add the possible margins to the sheet  = margin that exists unless price is increased 
        $priceChangeSheet = updateColumn($priceChangeSheet, $possibleMarginArray, $possibleMarginColumn);
        //add the new price change indicator to the sheet  
        $priceChangeSheet = updateColumn($priceChangeSheet, $priceChangeIndicatorArray, $priceChangeIndicatorColumn);

        //update ID in the cost cnage certificate
        $priceChangeSheet = addIDNum($priceChangeSheet);

        //save the new cost change list 
        $hrefdownloadSalePriceChange = saveSpreadsheetWithTimestamp($priceChangeSheet, $originalfileName,$rightToLeft=true,$suffix='_SALE_PRICE-CHANGE');
        //set a link to the screen to download the change cost  list file 
        echo '<a href="' . $hrefdownloadSalePriceChange . '" download>Download sale prices change file</a><br>';


    } else {
        echo "No prices change in the invoice and all last purchase costs prices were found<br>";
    }

} 


//move the OCR files (JSON and PDF to upload directory ) to the upload directory 
//build the file path
$ocrFileBaseName = $_SESSION['ocrFileBaseName'];
$ocrFilesDestinationDirectory = $shopData['ocrFilesDestinationDirectory'];
$ocrFilesFinalUploadDirectory = $shopData['ocrFilesFinalUploadDirectory'];

$ocdFilePathJSON = "$ocrFilesDestinationDirectory/$ocrFileBaseName.JSON";
$ocdFilePathpdf = "$ocrFilesDestinationDirectory/$ocrFileBaseName.pdf";
$uploadFilePathJSON = "$ocrFilesFinalUploadDirectory/$ocrFileBaseName.JSON"; 
$uploadFilePathpdf = "$ocrFilesFinalUploadDirectory/$ocrFileBaseName.pdf"; 


//move JSON file
if (rename($ocdFilePathJSON, $uploadFilePathJSON)) {
    echo "JSON OCR file moved successfully to upload directory <br>";
} else {
    echo "Failed to move JSON OCR file to upload directory <br>";
}
//move pdf file
if (rename($ocdFilePathpdf, $uploadFilePathpdf)) {
    echo "pdf invoice OCR file moved successfully to upload directory <br>";
} else {
    echo "Failed to move pdf invoice OCR file to upload directory <br>";
}

//indicators for the calculated ammounts 
$invsum = $_SESSION['invsum'];
$calcsum = $_SESSION['calcsum'];
echo "Invoice sum is $invsum <br>";
echo "Calculated sum is $calcsum <br>";


?>