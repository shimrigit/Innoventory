<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use \PhpOffice\PhpSpreadsheet\Cell\DataType;

function uploadAndConvertToSpreadsheet($filename,$filePath) {
    // Construct the full path to the file
    $file_path = $filePath .'/'. $filename;

    echo "File path $file_path <br>";

    // Check if the file exists
    if (file_exists($file_path)) {
        // Load the CSV file into a PhpSpreadsheet object
        $spreadsheet = IOFactory::load($file_path);
        return $spreadsheet;
    } else {
        // File does not exist
        die("file $filename does not exists in $filePath directory");
        //return null;
        
    }
}

//the function create an empty  stock progress table using start date and end date 
function createStockProgressTable($startDate, $endDate)
{
    // Calculate period length
    $periodLength = intval((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)) + 2;

    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $sheet->setCellValue('A2', 'מוצר');
    $sheet->setCellValue('B1', 'תאריך');

    //add border line under row 1 and 2
    $colorBlack = new Color('FF000000');
    $columnLetter = Coordinate::stringFromColumnIndex($periodLength+2);
    $sheet->getStyle('A2:'.$columnLetter.'2')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->setColor($colorBlack);
    //add border line in column B
    $sheet->getStyle('B1:B2')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK)->setColor($colorBlack);

    // Define an array to map numeric day of the week to its textual representation
    $daysOfWeek = ['שבת', 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי'];

    // Populate dates and days of the week
    $currentDate = strtotime($startDate)+(60 * 60 * 24); // Initialize with start date

    for ($i = 3; $i < $periodLength+3; $i++) {
        // Set date
        $dateCell = $sheet->getCell([$i, 1]);
        $dateCell->setValue(Date::PHPToExcel($currentDate));
        $dateCell->getStyle()->getNumberFormat()->setFormatCode('dd/mm/yyyy');

        // Get the day of the week (0 = שבת, 1 = שני, ..., שישי = Saturday)
        $date = date_create(date('Y-m-d', $currentDate));
        $dayOfWeek = $date->format('w');

        // Get the textual representation of the day of the week
        $dayOfWeekTextual = $daysOfWeek[$dayOfWeek];

        //echo $dayOfWeekTextual; // Output: Sunday
        
        // Set day of the week in Hebrew
        //$dayOfWeek = date('N', $currentDate-(60 * 60 * 24)); //$currentDate->format('l'); date('l', $currentDate-(60 * 60 * 24))
        //$hebrewDayOfWeek = $hebrewDays[$dayOfWeek];
        $sheet->getCell([$i, 2])->setvalue($dayOfWeekTextual);

        //if the day of week is Saturday then paint the column in light blue
        // Set the fill color for the column
        if ($dayOfWeek == 0) {
            $columnLetter = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle($columnLetter)->getFill()->setFillType(Fill::FILL_SOLID);
            $sheet->getStyle($columnLetter)->getFill()->getStartColor()->setARGB('40CCFFFF'); 
        }

        //autosize the column 
        $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);

        // Move to next day
        $currentDate = strtotime('+1 day', $currentDate);
    }


    return $spreadsheet;
}


//The function add the holidays according to holidays array and an empty stock progress spreadsheet 
function addIsraelHolidaysToStockProgressSheet(array $holidayPairs, Spreadsheet $spreadsheet) {
    $sheet = $spreadsheet->getActiveSheet();

    // Get the highest column index in row 1
    $highestColumnIndex = $sheet->getHighestColumn();

    // Convert the highest column index to an integer
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumnIndex);

    foreach ($holidayPairs as $holidayPair) {
        $holidayDate = DateTime::createFromFormat('d-m-Y', $holidayPair[0]); // Convert date string to DateTime object
        $holidayName = $holidayPair[1]; // Get holiday name

        // Check if the DateTime object was created successfully
        if (!$holidayDate) {
            echo "Error: Invalid date format for holiday: " . $holidayPair[0] . "\n";
            continue; // Skip to the next holiday pair
        }

        // Iterate through the column indexes
        for ($columnIndex = 3; $columnIndex <= $highestColumnIndex; $columnIndex++) { // Start from 3rd column (C)
            // Get the date value in the cell
            $cellValue = $sheet->getCell([$columnIndex, 1])->getValue();

            //echo "cellValue : $cellValue <br>";

            // Convert Excel date value to Unix timestamp
            $unixTimestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($cellValue);

            // Convert Unix timestamp to DateTime object
            $cellDate = new DateTime('@' . $unixTimestamp);

            // Convert the cell value to a DateTime object
            //$cellDate = new DateTime($cellValue);//DateTime::createFromFormat('d/m/Y H:i:s', $cellValue);

            // Check if the DateTime object was created successfully
            if (!$cellDate) {
                echo "Error: Invalid date format in cell (" . $columnIndex . ", 1)\n";
                continue; // Skip to the next column
            }

            //echo "Cell Date: " . $cellDate->format('d/m/Y') . ", Holiday Date: " . $holidayDate->format('d/m/Y') . "<br>";

            if ($cellDate && $cellDate->format('d-m-Y') === $holidayDate->format('d-m-Y')) {
                // If date found, print holiday name in row 2 of the same column
                $sheet->setCellValue([$columnIndex, 2], $holidayName);
                //paint the column in light red
                $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
                $sheet->getStyle($columnLetter)->getFill()->setFillType(Fill::FILL_SOLID);
                $sheet->getStyle($columnLetter)->getFill()->getStartColor()->setARGB('40FFCCE5');  
                //echo "Cell Date: " . $cellDate->format('d/m/Y') . ", Holiday Date: " . $holidayDate->format('d/m/Y') . "<br>";
                break; // Exit the loop once found
            }
        }
    }

    return $spreadsheet;
}

//the function will add to a given item in the row index  the Qty in the Qty row in the date column.
//the function will return false if dates are not in range (e.g. item date was not in the progress table range)
//IMPORTANT: The function will return true for success andfalse if date is not within date range 
//IMPORTANT: type = 1 = purchase value , type = 2 = sales value 
function addStockProgressItem(int $type, string $barcode, DateTime $date, int $quantity,  Spreadsheet $spreadsheet, int $rowIndex) {

    $sheet = $spreadsheet->getActiveSheet();
    //set itteration parameters
    $highestRow = $sheet->getHighestRow('A');
    $highestRowToSearch = $highestRow+1;

    //check that the date is in the range 
    $higestcoulmnLetter = $sheet->getHighestcolumn(1);
    $higestcoulmnIndex = Coordinate::columnIndexFromString($higestcoulmnLetter);
    $startDateSTR = $sheet->getCell('C1')->getValue();
    $endDateSTR = $sheet->getCell($higestcoulmnLetter.'1')->getValue();
    $startDate = Date::excelToDateTimeObject($startDateSTR);
    $endDate = Date::excelToDateTimeObject($endDateSTR);

    if ($date->format('Y-m-d') < $startDate->format('Y-m-d')) {
        //if the date is prevoius to start date - return false
        echo "Error - Date ".$date->format("Y-m-d")." is earlier that start date ".$startDate->format("Y-m-d")." for barcode $barcode<br>";
        return false;

    } elseif ($date->format('Y-m-d') > $endDate->format('Y-m-d')) {
        //if the date is after end date - return false
        echo "Error - Date ".$date->format("Y-m-d")."is after end date ".$endDate->format("Y-m-d")." for barcode $barcode<br>";
        return false;

    }

    //set the color and fill to mark cells which are not empty 
    $fillType = Fill::FILL_SOLID;
    $ARGBcolor = '40FFE5CC';

    //itterate through the column dates 
    for ($columnIndex = 3; $columnIndex <= $higestcoulmnIndex; $columnIndex++) {
        $dateInColumnSTR = $sheet->getCell([$columnIndex,1])->getValue();
        $dateInColumn = Date::excelToDateTimeObject($dateInColumnSTR);
        if ($date->format('Y-m-d') == $dateInColumn->format('Y-m-d')) {
            //echo "Input date is ".$date->format('Y-m-d')." column index is $columnIndex , row index is $rowIndex and Date is ".$dateInColumn->format('Y-m-d')."<br>";
            //if found  = barcode and date are in table and we can add quantity 
            //add the qty based on type
            if ($type == 1) { //IF PURCHASE  - process on the purchase cell 
                $cellExistingValue = $sheet->getCell([$columnIndex,$rowIndex])->getValue();
                if ($cellExistingValue != '') { //if the cell is not empty - add the 2 values together and paitn the cell
                    $sheet->setCellvalue([$columnIndex,$rowIndex],$quantity+$cellExistingValue);
                    $sheet->getCell([$columnIndex,$rowIndex])->getStyle()->getFill()->setFillType($fillType);
                    $sheet->getCell([$columnIndex,$rowIndex])->getStyle()->getFill()->getStartColor()->setARGB($ARGBcolor);  
                } else {
                    $sheet->setCellvalue([$columnIndex,$rowIndex],$quantity);
                }

                return true;
                
            } elseif ($type == 2) { //IF SALES   - process on the purchase cell 
                $cellExistingValue = $sheet->getCell([$columnIndex,$rowIndex+1]);
                if ($cellExistingValue != '') { //if the cell is not empty - add the 2 values together and paitn the cell
                    $sheet->setCellvalue([$columnIndex,$rowIndex+1],$quantity+(int)$cellExistingValue);
                    $sheet->getCell([$columnIndex,$rowIndex+1])->getStyle()->getFill()->setFillType($fillType);
                    $sheet->getCell([$columnIndex,$rowIndex+1])->getStyle()->getFill()->getStartColor()->setARGB($ARGBcolor);  
                } else {
                    $sheet->setCellvalue([$columnIndex,$rowIndex+1],$quantity);
                }

                return true;

            } else {
                //coding error - cloase the program
                die("Error - no data type is $type. Data type can be only 1 = Purchase or 2 = Sale");

            }
        }


    }

    //if function reached here - it is false 
    echo "Code Error in function";
    return false;

}



//The function will add a new product to the sales progress table in a given row
function addNewProductToTableInNewLine(int $rowForNewProduct, $ConfigObject, string $barcode, string $description, Spreadsheet $spreadsheet) {

    $sheet = $spreadsheet->getActiveSheet();
    //$highestRowIndex = $sheet->getHighestRow('A');
    $highestColumnLetter = $sheet->getHighestColumn(1);

    //get the names of the rows
    $row1Name = $ConfigObject['rowsNames'][0];
    $row2Name = $ConfigObject['rowsNames'][1];
    $row3Name = $ConfigObject['rowsNames'][2];
    $row4Name = $ConfigObject['rowsNames'][3];


    //add barcode in new line
    $sheet->getCell([1,$rowForNewProduct])->setvalue($barcode); 
    $sheet->setCellValueExplicit([1, $rowForNewProduct], $barcode, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    //echo "Add barcode $barcode to line $rowForNewProduct <br>";
    //add description in second line
    $sheet->getCell([1,$rowForNewProduct+1])->setvalue($description); 
    //add lines to support the new adition 
    $colorBlack = new Color('FF000000');
    $border = Border::BORDER_THICK;
    $lineIndex = $rowForNewProduct+3;
    //add a bottom line to the last row
    $sheet->getStyle('A'.$lineIndex.':'.$highestColumnLetter.$lineIndex)->getBorders()->getBottom()->setBorderStyle($border)->setColor($colorBlack);
    //add outline to column A and B
    $sheet->getStyle('A'.$rowForNewProduct.':A'.$lineIndex)->getBorders()->getRight()->setBorderStyle($border)->setColor($colorBlack);
    $sheet->getStyle('B'.$rowForNewProduct.':B'.$lineIndex)->getBorders()->getRight()->setBorderStyle($border)->setColor($colorBlack);
    //add the row names 
    $sheet->setCellValue([2,$rowForNewProduct],$row1Name);
    $sheet->setCellValue([2,$rowForNewProduct+1],$row2Name);
    $sheet->setCellValue([2,$rowForNewProduct+2],$row3Name);
    $sheet->setCellValue([2,$rowForNewProduct+3],$row4Name);

    //autofit for column A and B
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);


}

//the function will add the items in the sales or purchase spreadshseet to the ProgressTable
//IMPORTANT: type = 1 = purchase value , type = 2 = sales value 
function addItemsSheetToProgressTable(int $type, $orderRecommConfigObject, Spreadsheet $ItemsSpreadsheet, Spreadsheet $progressTable) {

    $ItemsSheet = $ItemsSpreadsheet->getActiveSheet();
    $progressTableSheet = $progressTable->getActiveSheet();

    //get highest row on item table 
    $ItemsSheetHighestRow = $ItemsSheet->gethighestrow('A');

    //get the columns for Barcode,date and Qty
    if ($type == 1) { //if purchase file 
        $barcodeColumnIndex = $orderRecommConfigObject["purchaseFileColumn"][0];
        $dateColumnIndex = $orderRecommConfigObject["purchaseFileColumn"][1];
        $qtyColumnIndex = $orderRecommConfigObject["purchaseFileColumn"][2];
        $descColumnIndex = $orderRecommConfigObject["purchaseFileColumn"][3];
    } elseif ($type == 2) { //if sales file
        $barcodeColumnIndex = $orderRecommConfigObject["salesFileColumn"][0];
        $dateColumnIndex = $orderRecommConfigObject["salesFileColumn"][1];
        $qtyColumnIndex = $orderRecommConfigObject["salesFileColumn"][2];
        $descColumnIndex = $orderRecommConfigObject["purchaseFileColumn"][3];
    } else {
        die("Error - type $type must be 1 for purchase or 2 for sales");
    }

    

    //set new products counter
    $productsCounter = 0;
    $itemsCounter = 0;
    //go over the items sheet and inset the items to the progressTable
    for ($itemRowIndex = 2; $itemRowIndex <= $ItemsSheetHighestRow; $itemRowIndex++) {
        //echo "start row $itemRowIndex <br>";
        //get the parameters from the row in items-sheet 
        $itemBarcode = $ItemsSheet->getCell([$barcodeColumnIndex,$itemRowIndex])->getValue();
        $itemDate = $ItemsSheet->getCell([$dateColumnIndex,$itemRowIndex])->getValue();
        $itemQty = $ItemsSheet->getCell([$qtyColumnIndex,$itemRowIndex])->getValue();
        $itemDesc = $ItemsSheet->getCell([$descColumnIndex,$itemRowIndex])->getValue();
        $iteDateObject = DateTime::createFromFormat('d/m/Y', $itemDate); //Date::excelToDateTimeObject($itemDate);

        //check if product exist - return the product line or 0 (zero) if not exists 
        $itemIndexInProgressTable = itemLocationInProgressTable($itemBarcode ,$progressTable);

        if ($itemIndexInProgressTable == 0) { //if return 0 - need to add the product and the item
            //add the new porduct to progress Table
            $PThigestRow = $progressTableSheet->gethighestrow('A');
            //echo "addNewProductToTableInNewLine line $PThigestRow+1 <br>";
            addNewProductToTableInNewLine($PThigestRow+1, $orderRecommConfigObject, $itemBarcode, $itemDesc, $progressTable);
            //increase products counter
            $productsCounter = $productsCounter+1;
            //add the item to the new product line
            //echo "addStockProgressItem line $PThigestRow+1 <br>";
            $returnVal = addStockProgressItem($type, $itemBarcode, $iteDateObject, $itemQty, $progressTable, $PThigestRow+1);
            //if the return value is false - then skip the 
            if ($returnVal == false) {
                echo "Date issue for item $itemBarcode in line $itemRowIndex - the item will be skkiped <br>";
            } else {
                //increase counter
                $itemsCounter = $itemsCounter+1;
            }

        } else { //if NOT return 0 add the item to that line the item
            $returnVal = addStockProgressItem($type, $itemBarcode, $iteDateObject, $itemQty, $progressTable, $itemIndexInProgressTable ); //$itemIndexInProgressTable - the row to add the item
            //if the return value is false - then skip the 
            if ($returnVal == false) {
                echo "Date issue for item $itemBarcode in line $itemRowIndex - the item will be skkiped <br>";
            } else {
                //increase counter
                $itemsCounter = $itemsCounter+1;
            }

        }



    }

    // Autofit column A
    //$progressTableSheet->getColumnDimension('A')->setAutoSize(true);

    echo "Total $productsCounter products were added to the Progress Table <br>";
    echo "Total $itemsCounter items were added to the Progress Table <br>";


}

//The function check the row of the barcode in the progressTable. if barcode not found it return zero
function itemLocationInProgressTable(string $barcode , Spreadsheet $progressTable) {

    //loop through the table from row 3 in jumps of 4
    $progressTableSheet = $progressTable->getActiveSheet();
    $progressTableHighestRow  = $progressTableSheet->gethighestRow('A');
    //for first row when highest row is 2 
    //if ($progressTableHighestRow == 2) {
    //    $progressTableHighestRow = 3;
    //}

    for ($itemRowIndex = 1; $itemRowIndex <= $progressTableHighestRow; $itemRowIndex++) {
        //get the barcode in the progressTable 
        $barcodeInPT = $progressTableSheet->getCell([1,$itemRowIndex])->getValue();
        if ($barcodeInPT == $barcode) {
            //if barcodes are equal - return the row number
            return $itemRowIndex;
        }

    }

    //if loop ends without finding the barcode - return 0 
    return 0;

}

function createStockPerformanceReport() {

    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Add headers to the first row
    $headers = [
        'ברקוד', 
        'תאור', 
        'מלאי נוכחי', 
        'ממוצע א', 
        'ממוצע ב', 
        'ממוצע ג', 
        'ממוצע ד', 
        'ממוצע ה', 
        'ממוצע ו', 
        'ממוצע ש',
        'יחידות להזמנה', 
        'אריזות להזמנה', 
        'הפסד עקב חוסר מלאי', 
        'ימים ללא מלאי', 
        'מוצר חלש', 
        'טעות בספירת מלאי', 
        'מלאי בפועל גדול ממלאי נספר'
    ];

    foreach ($headers as $index => $header) {
        //$sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        $sheet->setCellValue([$index + 1,1],$header);
    }

    return $spreadsheet;
}

function createSalesPerformanceReport() {

    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Add headers to the first row
    $headers = [
        'ברקוד', 
        'תאור', 
        'ממוצע א', 
        'ממוצע ב', 
        'ממוצע ג', 
        'ממוצע ד', 
        'ממוצע ה', 
        'ממוצע ו', 
        'ממוצע ש'
    ];

    foreach ($headers as $index => $header) {
        //$sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        $sheet->setCellValue([$index + 1,1],$header);
    }

    return $spreadsheet;
}

function addSupplierAndShopDataToProgressTable($supplier, $shopData, $orderRecommConfigObject, $suppliers_Spreadsheet, $stockProgressSalesSpreadsheet) {
    
    //get actiev sheet 
    $suppliersSheet = $suppliers_Spreadsheet->getActiveSheet();
    
    
    //get the supplier name and visit frequency column index from the config file
    $nameColumnIndex = $orderRecommConfigObject["suppliersFileColumn"][0];
    $visitFreqColumnIndex = $orderRecommConfigObject["suppliersFileColumn"][1];

    // Initialize the variable to store the visit frequency
    $suppliervisitFreq = null;
    $supplierFound = false;


    // Get the highest row number in the column
    $highestRow = $suppliersSheet->getHighestRow('A');

    // Iterate through each row in the column to find the row with the supplier name
    for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
        // Get the value of the cell in the name column for the current row
        $cellValue = $suppliersSheet->getCell([$nameColumnIndex, $rowIndex])->getValue();
        
        // Check if the cell value matches the supplier
        if ($cellValue == $supplier) {
            // Retrieve the value from the visit frequency column in the same row
            $suppliervisitFreq = $suppliersSheet->getCell([$visitFreqColumnIndex, $rowIndex])->getValue();
            
            // Set supplierFound to true
            $supplierFound = true;
            
            // Exit the loop once the supplier is found
            break;
        }
    }

    // Check if the supplier was found
    if ($supplierFound) {
        // Now $suppliervisitFreq contains the visit frequency for the specified supplier
        echo "Visit frequency for $supplier: $suppliervisitFreq <br>";
    } else {
        echo "Supplier $supplier not found <br>";
    }

    //get the rest day
    $restDay = $shopData["restDay"];
    //build the data string 
    $sheetDataSTR = "[$restDay,$suppliervisitFreq,$supplier][ספק,תדירות,מנוחה]";
    //add the supplier name and visit frequincy to progress sheet 
    $progressTableSheet = $stockProgressSalesSpreadsheet->getActiveSheet();
    $progressTableSheet->setCellValue([1,1],$sheetDataSTR);
    $progressTableSheet->getColumnDimension('A')->setAutoSize(true);


}

//CURRENTLY TAKING DATA FROM WRONG FILE - NEED TO FIX!!!!!!!!!!!!!!!!!!!!!!!!!!!
function addInventoryDataToProgressTable($orderRecommConfigObject, $products_Spreadsheet, $stockProgressSpreadsheet) {

    //get the active sheet and higest row 
    $stockProgressSheet = $stockProgressSpreadsheet->getActiveSheet();
    $highestRow = $stockProgressSheet->getHighestRow('A');


    for ($rowIndex = 3; $rowIndex <= $highestRow; $rowIndex += 4) {
            //get the barcode from the progress file
            $itemBarcode = $stockProgressSheet->getCell([1,$rowIndex])->getvalue();
            //get the data related to that barcode to the file
            $dataPair = getInventorySTRFromInventoryFile($itemBarcode,$orderRecommConfigObject,$products_Spreadsheet);
            //add the header STR to the progress file
            $stockProgressSheet->setCellValue([1,$rowIndex+2],$dataPair[0]);
            //add the data STR to the progress file
            $stockProgressSheet->setCellValue([1,$rowIndex+3],$dataPair[1]);
    }
        

}

//the function creates pair of strings related to a specific product according to its data in the inventory file
function getInventorySTRFromInventoryFile($barcode,$orderRecommConfigObject,$products_Spreadsheet) {

        //get the columns index from the $orderRecommConfigObject
        $barcodeColumnIndex = $orderRecommConfigObject["inventoryFileColumn"][0];
        $currentStockColumnIndex = $orderRecommConfigObject["inventoryFileColumn"][1];
        $purchasePriceColumnIndex = $orderRecommConfigObject["inventoryFileColumn"][2];
        $salePriceColumnIndex = $orderRecommConfigObject["inventoryFileColumn"][3];
        $unitsInPackColumnIndex = $orderRecommConfigObject["inventoryFileColumn"][4];
        $orderParameterColumnIndex = $orderRecommConfigObject["inventoryFileColumn"][5];

    
        // Get the active sheet of the inventory spreadsheet
        $sheet = $products_Spreadsheet->getActiveSheet();
    
        // Initialize variables to store item details
        $currentStock = null;
        $purchasePrice = null;
        $salePrice = null;
        $unitsInPack = null;
        $orderParameter = null;
        
        // Get the highest row number in the column
        $highestRow = $sheet->getHighestRow('A');
        $barcodeFound = false;
    
        // Iterate through each row in the column
        for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
            // Get the value of the cell in the barcode column for the current row
            $cellValue = $sheet->getCell([$barcodeColumnIndex, $rowIndex])->getValue();
            
            // Check if the cell value matches the itemBarcode
            if ($cellValue == $barcode) {
                // Retrieve the values from the specified columns in the same row
                $currentStock = $sheet->getCellByColumnAndRow($currentStockColumnIndex, $rowIndex)->getValue();
                $purchasePrice = $sheet->getCellByColumnAndRow($purchasePriceColumnIndex, $rowIndex)->getValue();
                $salePrice = $sheet->getCellByColumnAndRow($salePriceColumnIndex, $rowIndex)->getValue();
                $unitsInPack = $sheet->getCellByColumnAndRow($unitsInPackColumnIndex, $rowIndex)->getValue();
                $orderParameter = $sheet->getCellByColumnAndRow($orderParameterColumnIndex, $rowIndex)->getValue();

                //signal that the barcode was found
                $barcodeFound = true;
                
                // Exit the loop once the itemBarcode is found
                break;
            }
        }

        //create the pair of strings
        $headerSTR = "[מ.קניה , מ.מכירה ,מלאי נוכחי,יחידות באריזה ,מקדם הזמנה ]";
        if ($barcodeFound) {
            $dataSTR = "[$orderParameter, $unitsInPack,$currentStock,$salePrice,$purchasePrice]";
        } else { //if barcode not found - write ,essage in the file
            echo "Barcode $barcode not found in inventory file <br>";
            $dataSTR = "N/A";

        }
        
        return [$headerSTR,$dataSTR];

}

//the function will analyze the sales progress and inset the data to the performance report 
function processSalesPerformanceReport($salesPerformanceSpreadsheet, $progressReportSpreadsheet,$orderRecommConfigObject) {

    //get the parameters for the calculation 
    $progressReportSheet = $progressReportSpreadsheet->getActiveSheet();
    $salesPerformanceSheet = $salesPerformanceSpreadsheet->getActiveSheet();
    //$supplierRAWData  = $progressReportSheet->getCell([1,1])->getValue();

    //$supplierData = ["Sat",3,"Gad"];

    //$startDate = "";
    //$nextVisitDate = "";

    //iterate through the product progress sheet and calculate the average for each product 
    $progressSheetBarcodeRowIndex = 3;
    $performanceSheetRowIndex = 2;
    $barcode = $progressReportSheet->getCell([1,$progressSheetBarcodeRowIndex])->getValue();
    $productDescription = "initial";
    
    While ($barcode!="") {
        //get the product array
        $productArray = getSingleProductSalesMatrix($progressSheetBarcodeRowIndex+1,$progressReportSpreadsheet);
        //calculate and set in table the avarage weekly sale
        calcAverageWeekdaySale($performanceSheetRowIndex, $productArray, $salesPerformanceSpreadsheet);
        //write the barcode in the performance sheet product row
        //$salesPerformanceSheet->setCellValueByColumnAndRow(1, $performanceSheetRowIndex, $barcode);
        $salesPerformanceSheet->setCellValueExplicit([1, $performanceSheetRowIndex], $barcode, DataType::TYPE_STRING);
        //get the product description from the progress table and write in the performance sheet
        $productDescription = $progressReportSheet->getCell([1,$progressSheetBarcodeRowIndex+1])->getValue();
        $salesPerformanceSheet->setCellValueByColumnAndRow(2, $performanceSheetRowIndex, $productDescription);
        //promote the indexes 
        $progressSheetBarcodeRowIndex = $progressSheetBarcodeRowIndex+4;
        $performanceSheetRowIndex = $performanceSheetRowIndex+1;
        //get new barcode value 
        $barcode = $progressReportSheet->getCell([1,$progressSheetBarcodeRowIndex])->getValue();

    }

    //autosize barcode and description columns 
    $salesPerformanceSheet->getColumnDimensionByColumn(1)->setAutoSize(true);
    $salesPerformanceSheet->getColumnDimensionByColumn(2)->setAutoSize(true);
    //echo the end of process
    echo "Performance table process completed<br>";

    return $salesPerformanceSpreadsheet;

}

//get a single product single weekday sales matrix (e.g. array for Sunday , or Monday, etc) 
// - e.g. a matrix with number of product sold for a given weekday along the period 
function getSingleProductSingleWeekdayArray(int $rowIndex, int $columnIndex,$progressReportSpreadsheet) {

    //get the relevant sheet 
    $progressReportSheet = $progressReportSpreadsheet->getActiveSheet();
    // Get the highest column as a string (e.g., 'Z')
    $highestColumn = $progressReportSheet->getHighestColumn();
    //echo "highestColumn = $highestColumn <br>";

    // convert this column string to an index
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    //echo "highestColumnIndex = $highestColumnIndex <br>";
 
    //$highestcolumn = 58; //NEED TO EXTRACT FROM SPREADSHEET 
    $period = $highestColumnIndex - $columnIndex;
    //echo "period = $period <br>";

    //calculate the amout of relevant week 
    $weeksSum  = intdiv($period, 7)+1;
    //echo "weeksSum = $weeksSum <br>";

    //initiate the array of the weekday in the weeks in the period
    $weekDayArray = array_fill(0, $weeksSum, 0);
    //initiate the index for the array 
    $singleWeekdayMatrixIndex = 0;

    //iterate through the period to get the value of each day
    
    for ($i = 0; $i < $weeksSum; $i++) {

        //get the value to the array 
        $weekDayArray[$i] = $progressReportSheet->getCell([$columnIndex, $rowIndex])->getValue() ?? 0;
        //debug
        //echo "Itter $i:  rowIndex = $rowIndex , columnIndex = $columnIndex, value = $weekDayArray[$i] <br> ";
        //promote the columnIndex in 7 days
        $columnIndex = $columnIndex+7;

    } 

    //print the array for debug purpose 
    //print_r($weekDayArray);

    return $weekDayArray;

}

//the function build the array of arrays that represent the sales in each week day along the period
function getSingleProductSalesMatrix(int $rowIndex,$progressReportSpreadsheet) {

    //get the relevant sheet 
    $progressReportSheet = $progressReportSpreadsheet->getActiveSheet();
    // Get the highest column as a string (e.g., 'Z')
    $highestColumn = $progressReportSheet->getHighestColumn();
    //echo "highestColumn = $highestColumn <br>";
    // convert this column string to an index
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    //verify that the period is higher than 7 days (e.g. the $highestColumnIndex of the $progressReportSpreadsheet is larger than 8 )
    if  ($highestColumnIndex < 9) {
        //close the program
        $period =  $highestColumnIndex - 2;
        die ("Period is $period and it is smaller than 7 days");
    }

    //create the product array 
    $productArray = array_fill(0, 7, null);


    //itterate through columns 3 to 9 and create the getSingleProductSingleWeekdayArray for each one
    //after that allocate position in matrix according to the weekday number (e.g. Sun = 0, Mon = 1, etc)
    for ($columnIndex = 3; $columnIndex <= 9; $columnIndex++) {

        //get the day value
        $dateValue = $progressReportSheet->getCell([$columnIndex,1])->getValue();
        //Chang to DateTimeObject
        $date = Date::excelToDateTimeObject($dateValue);
        //get the date index 
        $weekdayIndex = $date->format('w');
        //create the weekday array 
        $singleWeekdayArray = getSingleProductSingleWeekdayArray($rowIndex,$columnIndex,$progressReportSpreadsheet);

        //allocate the single weekday array to the product array
        $productArray[$weekdayIndex] = $singleWeekdayArray;

    }

    //print the array for debug purposes 
    //var_dump($productArray);

    //reurn the array
    return $productArray; 

}

//this function get a single product sales array and calculate the average day sales to Sales performance report
function calcAverageWeekdaySale(int $productIndexInSalesPerformanceSpreadsheet, array $productArray, $salesPerformanceSpreadsheet,) {

    //get the relevant sheet 
    $salesPerformanceSheet = $salesPerformanceSpreadsheet->getActiveSheet();

    //itterate through the weekdays matrix 
    for ($i = 0; $i <= 6; $i++) {
        //get the day array
        $weekdayArray = $productArray[$i];

        // Calculate the sum of the array
        $sum = array_sum($weekdayArray);
        //echo "Sum $i is $sum <br>";

        // Calculate the count of the array
        $count = count($weekdayArray);
        //echo "Count $i is $count <br>";

        // Calculate the average
        if ($count > 0) {
            $average = $sum / $count;
            //echo "The average is: " . $average;
        } else {
            $average = 0;
        }

        //echo "Average $i is $average <br>";

        //insert the values into the $salesPerformanceSheet
        $salesPerformanceSheet->setCellValueByColumnAndRow($i+3, $productIndexInSalesPerformanceSpreadsheet, $average);

    }


}

//the function will use the sales performance and other parameters to calculate the order for each product 
function calculateOrder($salesPerformanceSpreadsheet,$stockProgressSpreadsheet,$calculationPeriodEndDate,$visitAfterTheNextDate) {

    //get the sheets 
    $salesPerformanceSheet = $salesPerformanceSpreadsheet->getActiveSheet();
    //$stockProgressSheet = $stockProgressSpreadsheet->getActiveSheet();

    //add the headline "יחידות להזמנה"
    $header = "יחידות להזמנה";
    $salesPerformanceSheet->setCellValueByColumnAndRow(10, 1, $header);


    //build the calculation array 
    $orderPeriodStartDate = new DateTime($calculationPeriodEndDate); //new DateTime('2023-10-01');
    $orderPeriodEndDate = new DateTime($visitAfterTheNextDate); //new DateTime('2023-10-08');
    //echo "calculationPeriodEndDate $calculationPeriodEndDate <br>";
    //echo "visitAfterTheNext $visitAfterTheNextDate <br>";

    // Calculate the number of days between the dates
    $interval = $orderPeriodStartDate->diff($orderPeriodEndDate)->days - 1;


    // Initialize the array with 2 rows
    $periodArray = [
        [],
        []
    ];

    // Populate the first row with dates
    $period = new DatePeriod($orderPeriodStartDate, new DateInterval('P1D'), $interval);

    foreach ($period as $date) {
        $periodArray[0][] = $date->format('Y-m-d');
        $periodArray[1][] = 0;
    }

    
    //go over the table ,calculate the order and inset to the cell
    $orderAmount = 0;
    $orderAmountColumn = 10;
    $performanceSheetRowIndex = 2;
    $barcode = $salesPerformanceSheet->getCell([1,$performanceSheetRowIndex])->getValue();
    
    While ($barcode!="") {
        //define the range
        $range = "C{$performanceSheetRowIndex}:I{$performanceSheetRowIndex}";
        // Use rangeToArray to get the values as an array
        $averageSalePerDayArray = $salesPerformanceSheet->rangeToArray($range, null, true, true, false);
        //calculate the order amount 
        $orderAmountRaw = calculatePurchasePerBarcode($periodArray,$averageSalePerDayArray[0]);
        //round up the unit value
        $orderAmount = ceil($orderAmountRaw);
        //set the order amount value 
        $salesPerformanceSheet->setCellValueByColumnAndRow($orderAmountColumn, $performanceSheetRowIndex, $orderAmount);
        //promote the row index 
        $performanceSheetRowIndex = $performanceSheetRowIndex+1;
        //set the new barcode value 
        $barcode = $salesPerformanceSheet->getCell([1,$performanceSheetRowIndex])->getValue();


    }




    //$rowIndex = 2;
    //$range = "C{$rowIndex}:I{$rowIndex}";
    // Use rangeToArray to get the values as an array
    //$averageSalePerDayArray = $salesPerformanceSheet->rangeToArray($range, null, true, true, false);

    //calculate the order amount 
    //calculatePurchasePerBarcode($periodArray,$averageSalePerDayArray[0]);



    // Output the resulting array
    //print_r($array);


}

//the function will take the 2 row array with dates and average sale array and will fill the average according  to the date
function calculatePurchasePerBarcode($periodArray,$averageSalePerDayArray) {

    foreach ($periodArray[0] as $index => $dateString) {
        // Convert date string to DateTime object
        $date = new DateTime($dateString);
        // Get the day of the week (0 for Sunday, 6 for Saturday)
        $dayOfWeek = $date->format('w');
        // Get the corresponding sales value
        $salesValue = $averageSalePerDayArray[$dayOfWeek];
        // Set the sales value in the second row of the periodArray
        $periodArray[1][$index] = $salesValue;
    }

    $orderAmount = array_sum($periodArray[1]);
    //print_r($periodArray[1]);
    //echo "<br>";
    //echo "orderAmount $orderAmount <br>";
    return $orderAmount;

}














?>