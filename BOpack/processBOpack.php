<?php
require '../vendor/autoload.php'; 
//include '../functions/configFunctions.php';
include 'BOpackFunctions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Access the additional parameters
    $shop = isset($_POST['shop']) ? $_POST['shop'] : null;
    //$supplier = isset($_POST['supplier']) ? $_POST['supplier'] : null;

    //get the process date 
    $processDate = isset($_POST['processDate']) ? $_POST['processDate'] : null;
    //Change the date format
    $processDate = date('d-m-y', strtotime($processDate));


    //get the shops config object
    $shopConfigObject = uploadJSONdata('../shops.json');
    $shopData = getShopForm($shopConfigObject,$shop);

    // Define the date, headers, and supplierParam (as per your input)
    //$processDate = '25-10-24';  // Example date, replace with actual
    echo "Process date is $processDate <br>";
    $supplierParam = $shopData['supplierParam'];
    $headersArray = $shopData['BOpackInvoiceListHeader'];
    $fetchForBOdirectory = $shopData['fetchForBOdirectory'];
    $BOoutputDirectory = $shopData['BOoutputDirectory'];
    $BOtempDirectory = $shopData['BOtempDirectory'];
    $priceChangeHeaders = $shopData['priceChangeHeader'];
    $BOtype = $shopData['BOtype']; //can be comax or all4shops
    $newProductsBOHeaders = $shopData['newProductsForBOheaders'];
    $supplierNameColumn = $shopData['supplierColumnAtNewProductsForBOHeaders'];
    

    // Call the function to process invoices
    echo "Start the process for file listing <br>";
    $invoiceGroups = processInvoicesByDate($processDate, $headersArray, $supplierParam,$fetchForBOdirectory,$BOoutputDirectory,$BOtempDirectory);

    //create invoices to upload 
    prepareInvoicesForUpload($invoiceGroups, $BOoutputDirectory,$BOtype,$shopData);

    //create sale price change sheet 
    preparePriceChangeSheetForBOUpload($invoiceGroups, $BOoutputDirectory, $priceChangeHeaders,'sale',$BOtype,$shopData);

    //create new products sheet to upload 
    prepareNewProductsSheetForBOUpload($invoiceGroups, $BOoutputDirectory, $newProductsBOHeaders,$processDate,$supplierNameColumn);

    //send email/draft with BO results to customer 
    //emailBOResultsToCustomer($shopData);
    //***************** PARTLY WORKING - WILL BE DEBUGED WHEN NEEDED * CURRENTLY ""toSendToCustomer": 0," so the function desabled/



} else {
    echo "<p>Error: Invalid request.</p>";
}



?>
