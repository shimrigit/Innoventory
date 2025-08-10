
<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;


//mapping function - get a file and headers array and mapping array and types and return a new file based on that
function filterPriceChanges(Spreadsheet $backOfficeSheet, $diffColumn, float $threshold) {
    // Create a new Spreadsheet for the filtered price changes
    $priceChangeList = new Spreadsheet();
    $newSheet = $priceChangeList->getActiveSheet();

    // Get the active sheet from backOfficeSheet
    $originalSheet = $backOfficeSheet->getActiveSheet();
    $highestRow = $originalSheet->getHighestRow();
    $highestColumnString = $originalSheet->getHighestColumn();
    
    // Convert highest column string (e.g., "D") to a column index (e.g., 4)
    $highestColumn = Coordinate::columnIndexFromString($highestColumnString);

    // Copy the headers from the original sheet to the new sheet
    for ($col = 1; $col <= $highestColumn; $col++) {
        // Convert column number to letter
        $columnLetter = Coordinate::stringFromColumnIndex($col);
        $headerValue = $originalSheet->getCell($columnLetter . '1')->getValue();
        $newSheet->setCellValue($columnLetter . '1', $headerValue);
    }

    $newRowIndex = 2; // Start after headers (row 1)

    // Iterate over each row in the original sheet
    for ($row = 2; $row <= $highestRow; $row++) {
        // Convert diffColumn number to letter
        $diffColumnLetter = Coordinate::stringFromColumnIndex($diffColumn);
        // Get the difference value in the specified column
        $diffValue = $originalSheet->getCell($diffColumnLetter . $row)->getValue();

        // Check if the value is numeric before applying abs()
        if (is_numeric($diffValue)) {
            if (abs($diffValue) > ($threshold/100)) {
                // Copy the entire row to the new sheet
                for ($col = 1; $col <= $highestColumn; $col++) {
                    // Convert column number to letter
                    $columnLetter = Coordinate::stringFromColumnIndex($col);
                    $cellValue = $originalSheet->getCell($columnLetter . $row)->getValue();
                    $newSheet->setCellValue($columnLetter . $newRowIndex, $cellValue);
                }
                $newRowIndex++;
            }

        } else if ($diffValue == "No PL Price") { //else if not numeric but have the value "No PL Price"
            // Copy the entire row to the new sheet
            for ($col = 1; $col <= $highestColumn; $col++) {
                // Convert column number to letter
                $columnLetter = Coordinate::stringFromColumnIndex($col);
                $cellValue = $originalSheet->getCell($columnLetter . $row)->getValue();
                $newSheet->setCellValue($columnLetter . $newRowIndex, $cellValue);
            }
            $newRowIndex++;

        }
    }

    //check if the sheet is empty e.g. the higest row is 1 then return null otherwise retur the new Spreadhseet
    $highestRow = $newSheet->getHighestRow();
    if ($highestRow == 1) {
        return null;
    } else {
        return $priceChangeList;
    }
}

//mapping function with original sheet , headers array and mapping array 
function mapOriginalSheetToNewSheet(Spreadsheet $originalSheet, array $headersArry, array $mappingArray): Spreadsheet {
    // Create a new spreadsheet
    $newMappedSheet = new Spreadsheet();
    $newSheet = $newMappedSheet->getActiveSheet();

    // Set the new headers in row 1 of the new sheet
    foreach ($headersArry as $colIndex => $header) {
        $columnLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
        $newSheet->setCellValue($columnLetter . '1', $header);
    }

    // Iterate over the mapping array to copy the data from originalSheet to newSheet
    foreach ($mappingArray as $map) {
        $origColumn = $map[0];
        $destColumn = $map[1];
        $dataType = $map[2];

        // Get the highest row number in the original sheet
        $highestRow = $originalSheet->getActiveSheet()->getHighestRow();

        // Loop through all rows starting from the second one to map the data
        for ($row = 2; $row <= $highestRow; $row++) {
            // Convert column index to Excel letter
            $origColumnLetter = Coordinate::stringFromColumnIndex($origColumn);
            $destColumnLetter = Coordinate::stringFromColumnIndex($destColumn);
            $originalValue = $originalSheet->getActiveSheet()->getCell($origColumnLetter . $row)->getValue();

            // Apply data type conversion if needed
            if ($dataType == 2) {
                // Set as string
                $newSheet->setCellValueExplicit($destColumnLetter . $row, (string)$originalValue, DataType::TYPE_STRING);
            } else {
                // Set without any change
                $newSheet->setCellValue($destColumnLetter . $row, $originalValue);
            }
        }
    }

    //autofit the 4 first columns
    $newSheet->getColumnDimension('A')->setAutoSize(true);
    $newSheet->getColumnDimension('B')->setAutoSize(true);
    $newSheet->getColumnDimension('C')->setAutoSize(true);
    $newSheet->getColumnDimension('D')->setAutoSize(true);

    return $newMappedSheet;
}

//the function holds the operation for price change recommendation based on cost change 
function SalePriceChangeGenerator(Spreadsheet $sheet, int $originalCostColumn, 
        int $newCostColumn, int $originalSalePriceColumn,
        array $recommendedMarginArray ,float $VATfactor,
        float $priceChangeThresholdPercentage,int $barcodeColumn, 
        $supervisionJSON,float $maxExtraMargin): array {

    // Initialize the array to hold new sale prices
    $newSalePriceArray = [];
    //initelize current product Margin array 
    $originalMarginArray = [];
    //initelize possible margin array (after cost change without price change)
    $possibleMarginArray = [];
    //initelize price chnage indicator array 
    $priceChangeIndicatorArray = [];

    // Get the active sheet from the provided Spreadsheet
    $worksheet = $sheet->getActiveSheet();

    // Get the highest row to iterate over the rows
    $highestRow = $worksheet->getHighestRow();

    // Iterate over each row (starting from 2 assuming row 1 is headers)
    for ($row = 2; $row <= $highestRow; $row++) {
        // Convert column index to Excel letter equivalent
        $originalCostColumnLetter = Coordinate::stringFromColumnIndex($originalCostColumn);
        $newCostColumnLetter = Coordinate::stringFromColumnIndex($newCostColumn);
        $originalSalePriceColumnLetter = Coordinate::stringFromColumnIndex($originalSalePriceColumn);
        $barcodeColumnLetter = Coordinate::stringFromColumnIndex($barcodeColumn);

        // Get the original cost, new cost , original sale price and 
        $originalCost = $worksheet->getCell($originalCostColumnLetter . $row)->getValue();
        $newCost = $worksheet->getCell($newCostColumnLetter . $row)->getValue();
        $originalSalePrice = $worksheet->getCell($originalSalePriceColumnLetter . $row)->getValue();
        $barcode = $worksheet->getCell($barcodeColumnLetter . $row)->getValue();
        //get the reference recommended margin
        $itemRecommendedMargin = $recommendedMarginArray[$row - 2];

        //fixing the cases where the original price is missing
        //we give the new cost and let the calculation be over that cost 
        //it will sort a new price if the new margin is lower than recommended 
        if ($originalCost == "") {
            $originalCost = $newCost;
        }

        //check if the barcode/item is in the list of supervised products 
        $isPriceSupervised = checkIfPriceSupervised($barcode,$supervisionJSON);

        // claculate the values for the Sale Price 
        //first  - Check if the original cost and original sale price are valid numbers
        if (is_numeric($originalCost) && is_numeric($newCost) && is_numeric($originalSalePrice) && $originalCost != 0 && is_numeric($itemRecommendedMargin)) {
            // Calculate the percentage increase in cost
            //$costIncreasePercentage = $newCost / $originalCost;

            //************ originl margin******** 
            //calculate the original margin "from above" {(salePrice-costPrice)/salePrice}
            $netSalePrice = $originalSalePrice/$VATfactor;
            $originalMargin = ($netSalePrice -$originalCost)/$netSalePrice;
            // Store the margin in the array
            $originalMarginArray[$row - 2] = $originalMargin;

            //**************** possible margin */
            //the margin that exists if we do not increase price or could have exists if we increase pricse
            $possibleMargin  = ($netSalePrice -$newCost)/$netSalePrice;
            $possibleMarginArray[$row - 2] = $possibleMargin;

            //*************** price change algorithm process */
            //Max Extra Margin = config value which represent the maxium margin above recommended 
            //"X" =  new cost + recommended margin
            //"Y" = new cost + recommended margin + Max Extra Margin
            //if "X" is higher than current sale price 
            //THEN "X" is the new Sale price 
            //if current sale price  is higer that "Y" 
            //THEN "Y" is the new Sale price
            $recommendedSalePrice = ($newCost/(1-$itemRecommendedMargin))*$VATfactor;
            $recommendedSalePrice = ceil($recommendedSalePrice*10)/10; // round up sale price to 1 deciaml location 
            $maxSalePrice = ($newCost/(1-($itemRecommendedMargin+$maxExtraMargin)))*$VATfactor;
            if ($isPriceSupervised) { //check if price is supervised 
                $newSalePriceArray[$row - 2] = $originalSalePrice;
                $priceChangeIndicatorArray[$row - 2] = "Supervised";
            } elseif ($recommendedSalePrice > $originalSalePrice) {
                //sale price is the cost + recommneded margin
                $SalePrice = $recommendedSalePrice;
                $newSalePriceArray[$row - 2] = $SalePrice;
                $priceChangeIndicatorArray[$row - 2] = "YES.Increase"; //original "YES"
                //exception where the diff between $originalSalePrice and $recommendedSalePrice is too small
                if ($recommendedSalePrice < $originalSalePrice*(1+($priceChangeThresholdPercentage/100))) {
                    $multiplexer = 1+($priceChangeThresholdPercentage/100);
                    echo "recommnded sale price $recommendedSalePrice, Original sale price $originalSalePrice , myltiplexer $multiplexer<br>";
                    //Just chשמge the recommendation  and keep the old price
                    $newSalePriceArray[$row - 2] = $originalSalePrice;
                    $priceChangeIndicatorArray[$row - 2] = "NO. Slim change";
                }
            } elseif ($maxSalePrice < $originalSalePrice) { 
                // if the origianl sale price is higher than max sell price then reduce to max sell price
                $maxSalePrice = ceil($maxSalePrice*10)/10; //round up sale price to 1 deciaml location 
                $newSalePriceArray[$row - 2] = $maxSalePrice;
                $priceChangeIndicatorArray[$row - 2] = "YES.Decrease";
            } else {
                $newSalePriceArray[$row - 2] = $originalSalePrice;
                $priceChangeIndicatorArray[$row - 2] = "NO";
            }

            

        } else {
            // If values are invalid, store null or some error flag
            $newSalePriceArray[$row - 2] = null; 
            $originalMarginArray[$row - 2] = null;
            $possibleMarginArray[$row - 2] = null;
            $priceChangeIndicatorArray[$row - 2] = null;
            //if the problem was that no recommneded margin or department was found
            if (!is_numeric($itemRecommendedMargin)) {
                $priceChangeIndicatorArray[$row - 2] = "No Department/recomm Margin found";
                echo "in row $row of price change sheet the Department is missing or missing value at Department config file <br>";

            } 
            
        }
    }

    //return all 4 arrays
    return [
        $newSalePriceArray,
        $originalMarginArray,
        $possibleMarginArray,
        $priceChangeIndicatorArray
    ];
}

//the function add the sales price and margine to Sales change sheet 
function updateColumn(Spreadsheet $spreadsheet, array $itemsArray, int $columnToUpdate): Spreadsheet {
    // Get the active sheet from the spreadsheet
    $sheet = $spreadsheet->getActiveSheet();

    // Determine the starting row (let's assume it starts at row 2, change as needed)
    $startRow = 2;

    // Loop through the items array and update the specified column by row order
    foreach ($itemsArray as $index => $item) {
        $row = $startRow + $index;  // Determine the row to update
        
        // Convert the column number to its Excel letter equivalent
        $columnLetter = Coordinate::stringFromColumnIndex($columnToUpdate);

        // Set the value in the cell
        $sheet->setCellValue($columnLetter . $row, $item);
    }

    // Return the updated spreadsheet
    return $spreadsheet;
}

function addIDNum(Spreadsheet $PriceChangeSheetToBO): Spreadsheet {
    // Get the active sheet
    $sheet = $PriceChangeSheetToBO->getActiveSheet();

    // Get the highest row in column B
    $highestRow = $sheet->getHighestRow('B');

    // Iterate from row 2 to the highest row and add consecutive numbers to column A
    for ($row = 2; $row <= $highestRow; $row++) {
        // Set the consecutive number in column A
        $sheet->setCellValue('A' . $row, $row - 1);
    }

    return $PriceChangeSheetToBO;
}

//the function check the department on pricechnage list and store the recommended margin in an array 
function generateRecommendedMarginArray($priceChangeList, $departmentConfigObject, $departmentColumn) {
    // Step 1: Create an empty array to store the recommended margins
    $recommendedMarginArray = [];

    // Step 2: Access the worksheet from the PhpSpreadsheet object
    $worksheet = $priceChangeList->getActiveSheet();

    // Step 3: Get the highest row number to iterate through all rows in the department column
    $highestRow = $worksheet->getHighestRow();

    // Step 4: Loop over each row in the specified column
    for ($row = 2; $row <= $highestRow; $row++) { // Assuming data starts from row 2
        $departmentName = $worksheet->getCellByColumnAndRow($departmentColumn, $row)->getValue();

        // Step 5: Handle the case when the department value is empty
        if (empty($departmentName)) {
            $recommendedMarginArray[] = "Product without Department";
            continue;
        }

        // Step 6: Search for the matching department in the configuration object
        $matchedMargin = null;
        foreach ($departmentConfigObject as $department) {
            if ($department['DepartmentName'] === $departmentName) {
                $matchedMargin = $department['ExpectedMarginPercentage'];
                break; // Exit the loop once a match is found
            }
        }

        // Step 7: Add appropriate value to the array based on search result
        if ($matchedMargin !== null) {
            $recommendedMarginArray[] = $matchedMargin;
        } else {
            $recommendedMarginArray[] = "Department not found";
        }
    }

    // Step 8: Return the array of recommended margins
    return $recommendedMarginArray;
}

function generateInvoiceIdentifierFromFileName($originalfileName) {
    // Check if the "_OCRsanity" string exists in the file name
    $sanityPosition = strpos($originalfileName, '_OCRsanity');
    if ($sanityPosition === false) {
        echo "Invoice identifier was not found";
        return null;
    }

    // Extract the substring before "_OCRsanity"
    $beforeSanity = substr($originalfileName, 0, $sanityPosition);

    // Find the last underscore before "_OCRsanity"
    $lastUnderscorePosition = strrpos($beforeSanity, '_');
    if ($lastUnderscorePosition === false) {
        echo "Invoice identifier was not found";
        return null;
    }

    // Extract the invoice identifier without trimming spaces
    $invoiceIdentifier = substr($beforeSanity, $lastUnderscorePosition + 1);

    // Return the result as is
    return $invoiceIdentifier;
}

//populate a column from row 2 with a single given value 
function insertValueToColumnFrom2(string $value, int $Column, Spreadsheet $Spreadsheet) {
    // Get the active worksheet from the spreadsheet
    $worksheet = $Spreadsheet->getActiveSheet();

    // Get the highest row number in the spreadsheet
    $highestRow = $worksheet->getHighestRow();

    // Loop through each row in the specified column
    for ($row = 2; $row <= $highestRow; $row++) { // Assuming you want to populate all rows
        $worksheet->getCell([$Column, $row])->setValue($value);
    }
}

//the function check if the barcode is in the supervisied products list and return true if so 
function checkIfPriceSupervised($barcode, $supervisionArray) {

    // Get the last 6 digits of the barcode as a string
    $barcodeLast6 = substr($barcode, -6);

    foreach ($supervisionArray as $item) {
        // Get the last 6 digits of the item's Barcode field
        $itemBarcodeLast6 = substr($item['Barcode'], -6);

        if ($barcodeLast6 == $itemBarcodeLast6) {
            echo "Item {$item['ItemName']} is supervised <br>";
            return true;
        }
    }

    return false;
}

?>