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
    

    //upload the order recommnend config file
    $orderRecommConfigObject = uploadJSONdata('orderRecommconfig.json');
    $jxset_File_Path = $orderRecommConfigObject['jxset_directory_path'];
    //$purchase_filename = $orderRecommConfigObject["purchase_filename"];
    $sales_filename = $orderRecommConfigObject["sales_filename"];
    //$inventory_filename = $orderRecommConfigObject["inventory_filename"];
    $products_filename = $orderRecommConfigObject["products_filename"];
    $suppliers_filename = $orderRecommConfigObject["suppliers_filename"];
        

    // Upload and convert each file to PhpSpreadsheet objects
    //$purchase_Spreadsheet = uploadAndConvertToSpreadsheet($purchase_filename,$jxset_File_Path);
    $sales_Spreadsheet = uploadAndConvertToSpreadsheet($sales_filename,$jxset_File_Path);
    //$inventory_Spreadsheet = uploadAndConvertToSpreadsheet($inventory_filename,$jxset_File_Path);
    $products_Spreadsheet = uploadAndConvertToSpreadsheet($products_filename,$jxset_File_Path);
    $suppliers_Spreadsheet = uploadAndConvertToSpreadsheet($suppliers_filename,$jxset_File_Path);


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
    $endDate = date('Y-m-d', strtotime($orderDate . ' -2 day'));
    $nextVisitDate = isset($_POST['nextVisitDate']) ? $_POST['nextVisitDate'] : null;
    $visitAfterTheNextDate = isset($_POST['visitAfterTheNextDate']) ? $_POST['visitAfterTheNextDate'] : null;


    //get the shops config object
    $shopConfigObject = uploadJSONdata('../shops.json');
    $shopData = getShopForm($shopConfigObject,$shop);


    //create stock-progress table
    $stockProgressSpreadsheet = createStockProgressTable($startDate, $endDate);

    //add the holidays 
    $holidayPairs = $orderRecommConfigObject["holidays"];
    $stockProgressSpreadsheet = addIsraelHolidaysToStockProgressSheet($holidayPairs, $stockProgressSpreadsheet);

    //add the supplier and shop data to progress table - name, frequincy of visit, etc
    addSupplierAndShopDataToProgressTable($supplier, $shopData, $orderRecommConfigObject, $suppliers_Spreadsheet, $stockProgressSpreadsheet);

    //add sales sheet  to progress table - e.g. insert the sales data of one supplier to the table
    addItemsSheetToProgressTable(2, $orderRecommConfigObject, $sales_Spreadsheet, $stockProgressSpreadsheet);

    //add the items data to the progress table - add the data about the products to the table (price, stock-visit ratio)
    addInventoryDataToProgressTable($orderRecommConfigObject, $products_Spreadsheet, $stockProgressSpreadsheet);

    //Save the stock progress table
    $hrefdownloadPathStockProgess = saveSpreadsheetToXLSXWithTimestamp($stockProgressSpreadsheet, 'jxset','STOCK_PROGRESS',$rightToLeft=false);

    //set a link to the screen to download the Comax file
    echo '<a href="' . $hrefdownloadPathStockProgess . '" download>Download stock progress file</a><br>';

    
    //create the sales performance report
    $salesPerformanceSpreadsheet = createSalesPerformanceReport();

    //process sales performance report
    processSalesPerformanceReport($salesPerformanceSpreadsheet, $stockProgressSpreadsheet,$orderRecommConfigObject);

    $orderCalculation = calculateOrder($salesPerformanceSpreadsheet,$stockProgressSpreadsheet,$orderDate,$visitAfterTheNextDate);

    //Save the stock performance report
    $hrefdownloadPathStockPerformance = saveSpreadsheetToXLSXWithTimestamp($salesPerformanceSpreadsheet, 'jxset','STOCK_PERFORMANCE',$rightToLeft=false);

    //set a link to the screen to download the stock performance report
    echo '<a href="' . $hrefdownloadPathStockPerformance  . '" download>Download sales performace and order file</a><br>';

    //create the order calculate sheet (based on sales)
    //$orderCalculation = calculateOrder($salesPR,$stockProgressSpreadsheet,$orderDate,$visitAfterTheNextDate);


} else {
    echo "<p>Error: Invalid request.</p>";
}

?>

    