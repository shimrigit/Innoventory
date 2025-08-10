<?php
//include the nessasery files
require __DIR__ . '/../vendor/autoload.php';
include __DIR__. '/../functions/configFunctions.php';
include 'orderRecommFunctions.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    
    //check what upload message was chossen 
    $uploadMethod = isset($_POST['uploadMethod']) ? $_POST['uploadMethod'] : null; 

    echo "upload method  = $uploadMethod <br>";

    //upload the order recommnend config file
    $orderRecommConfigObject = uploadJSONdata('orderRecommconfig.json');
    $jxset_File_Path = $orderRecommConfigObject['jxset_directory_path'];
    $purchase_filename = $orderRecommConfigObject["purchase_filename"];
    $sales_filename = $orderRecommConfigObject["sales_filename"];
    $inventory_filename = $orderRecommConfigObject["inventory_filename"];
    $products_filename = $orderRecommConfigObject["products_filename"];
    $suppliers_filename = $orderRecommConfigObject["suppliers_filename"];

    if ($uploadMethod == 'files') {
        //check files were set 
        if (isset($_FILES["purchase"]) ) { //&& isset($_FILES["sales"]) && isset($_FILES["inventory"]) && isset($_FILES["products"]) && isset($_FILES["suppliers"])) {
            //get the files from the POST message 
            $purchase_File = $_FILES["purchase"]["tmp_name"];
            $sales_File = $_FILES["sales"]["tmp_name"];
            $inventory_File = $_FILES["inventory"]["tmp_name"];
            $products_File = $_FILES["products"]["tmp_name"];
            $suppliers_File = $_FILES["suppliers"]["tmp_name"];

            //convert the files to Spreadsheets 
            $purchase_Spreadsheet = IOFactory::load($purchase_File);
            $sales_Spreadsheet = IOFactory::load($sales_File);
            $inventory_Spreadsheet = IOFactory::load($inventory_File);
            $products_Spreadsheet = IOFactory::load($products_File);
            $suppliers_Spreadsheet = IOFactory::load($suppliers_File);

            echo "All files were uploaded of FILES method<br>";
        } else {
            die('Could not upload files');
        }

        //if the user choose to upload from directory
    } elseif ($uploadMethod == 'directory') { 

        //build the path to the files from jxset directory 
        

        // Upload and convert each file to PhpSpreadsheet objects
        $purchase_Spreadsheet = uploadAndConvertToSpreadsheet($purchase_filename,$jxset_File_Path);
        $sales_Spreadsheet = uploadAndConvertToSpreadsheet($sales_filename,$jxset_File_Path);
        $inventory_Spreadsheet = uploadAndConvertToSpreadsheet($inventory_filename,$jxset_File_Path);
        $products_Spreadsheet = uploadAndConvertToSpreadsheet($products_filename,$jxset_File_Path);
        $suppliers_Spreadsheet = uploadAndConvertToSpreadsheet($suppliers_filename,$jxset_File_Path);

        echo "All files were uploaded on DIRECTORY method<br>";

    } else {
        die('No upload method was set.');
    }

    //test that all spreadsheets were upload correctly 
    //$cellValueA = $purchase_Spreadsheet->getActiveSheet()->getCell('A1')->getValue();
    //$cellValueB = $purchase_Spreadsheet->getActiveSheet()->getCell('B1')->getValue();
    //$cellValueC = $purchase_Spreadsheet->getActiveSheet()->getCell('C1')->getValue();
    //$cellValueD = $purchase_Spreadsheet->getActiveSheet()->getCell('D1')->getValue();
    //$cellValueE = $purchase_Spreadsheet->getActiveSheet()->getCell('E1')->getValue();
    //echo "cellValueA: $cellValueA, cellValueB: $cellValueB, cellValueC: $cellValueC, cellValueD: $cellValueD, cellValueE: $cellValueE <br>";

    // Access the additional parameters
    $shop = isset($_POST['shop']) ? $_POST['shop'] : null;
    $supplier = isset($_POST['supplier']) ? $_POST['supplier'] : null;

    // Access the time parameters (startDate and orderDate)
    $startDate = isset($_POST['startDate']) ? $_POST['startDate'] : null;
    $orderDate = isset($_POST['orderDate']) ? $_POST['orderDate'] : null;

    //get the shops config object
    $shopConfigObject = uploadJSONdata('../shops.json');
    $shopData = getShopForm($shopConfigObject,$shop);

    //$Adminstrator = $shopData["administratorName"];

    //echo "Shop: $shop Supplier: $supplier Administrator: $Adminstrator<br>";
    //echo "Start Date: $startDate Order Date: $orderDate<br>";
        /*
        //get the suppliers config object 
        //$supplierConfigObject = uploadJSONdata('suppliers.json');

        //get the config realtive path 
        $uploadRelativePath = $shopConfigObject['upload_relative_path']; 
        $downloadRelativePath = $shopConfigObject['download_relative_path'];

        // Move uploaded files to a suitable location
        move_uploaded_file($_FILES["OCRsanityFile"]["tmp_name"], "$uploadRelativePath/$OCRSanityFile");
        move_uploaded_file($_FILES["priceListFile"]["tmp_name"], "$uploadRelativePath/$priceListFile");

        // Display the file names on a new page
        echo "<h1>OCRsanity file: $OCRSanityFile<br></h1>";
        echo "<h1>Price List file: $priceListFile <br></h1>";

        //build the file path
        $OCRsanityfilePath = "$uploadRelativePath/$OCRSanityFile";
        $PriceListfilePath = "$uploadRelativePath/$priceListFile";
        $ComaxAfterPriceListcheckPath = "$downloadRelativePath/$OCRSanityFile";

        //load the file OCRsanity file  and convert the spreadsheet 
        $OCRSanitySpreadsheet = IOFactory::load($OCRsanityfilePath);

        //extract the supplier from the OCRSanity spreadsheet 
        $supplier = $OCRSanitySpreadsheet->getActiveSheet()->getCell([2,2])->getValue(); 
        $supplierJsonData = uploadJSONdata('suppliers.json');

        //get the supplier data from the config file 
        $supplierData = getSupplierData($supplierJsonData, $supplier);

        //generate Comax format from the OCRsanity format 
        //$ComaxSpreadsheet = processToComax($OCRSanitySpreadsheet, 'shops.json', 'JR_Topmarket');
        $ComaxSpreadsheet = processToPriceListBasicSheet($OCRSanitySpreadsheet,$shopData);
        //saveSpreadsheetWithTimestamp($ComaxSpreadsheet, $filePath,$rightToLeft=true,$suffix='_Comax');

        //run the pricelist analyze over the Comax format 
        //$ComaxAfterPriceListcheck = processPriceListcheck($ComaxSpreadsheet,'shops.json', 'JR_Topmarket',$PriceListfilePath,$supplier); 
        $ComaxAfterPriceListcheck = processPriceListcheckNew($ComaxSpreadsheet,$shopData,$PriceListfilePath);

        //Save the pricelist analyzed file 
        $hrefdownloadPathComax = saveSpreadsheetWithTimestamp($ComaxAfterPriceListcheck, $ComaxAfterPriceListcheckPath,$rightToLeft=true,$suffix='_PL');

        //set a link to the screen to download the Comax file
        echo '<a href="' . $hrefdownloadPathComax . '" download>Download Comax post-processed file</a><br>';

        //run the new product analyzing and create a list of new productrs. "NOT FOUND" column is 9. 
        //$newProductsList = creatListOfNewProductsBasic($ComaxAfterPriceListcheck,9);
        //$newProductsList = creatListOfNewProducts($ComaxAfterPriceListcheck,9,'suppliers.json','shops.json',$supplier,'JR_Topmarket'); //(set $shop properly!!!!)
        $newProductsList = creatListOfNewProductsNew($ComaxAfterPriceListcheck,9,$shopData,$supplier);

        //if the list is empty - echo . else save and set the link to the screen 
        if ($newProductsList!=null) {
            //save the new products list 
            $hrefdownloadNewProductrs = saveSpreadsheetWithTimestamp($newProductsList, $ComaxAfterPriceListcheckPath,$rightToLeft=true,$suffix='_NEW-PRODUCTS');

            //set a link to the screen to download the New products list file 
            echo '<a href="' . $hrefdownloadNewProductrs . '" download>Download new products file</a><br>';


        }
        
        createEmailComaxFiles($OCRSanitySpreadsheet,$supplierData,$shopData);

        */


} else {
    echo "<p>Error: Invalid request.</p>";
}
?>