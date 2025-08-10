<?php
require '../vendor/autoload.php'; 
include '../functions/configFunctions.php';
include '../emailMessages/generateEmailMessage.php';
require '../gmailAccess/gmail_functions.php';
require '../functions/convertJsonToExcel.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// Main processing function
function processInvoicesByDate($processDate, $headersArray, $supplierParam,$directory,$outputDirectory,$tempDirectory) {


    // Ensure the output and temp directories exist
    if (!file_exists($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }
    if (!file_exists($tempDirectory)) {
        mkdir($tempDirectory, 0777, true);
    }

    // Pattern to match files for the given date in the new format
    $pattern = '/([A-Za-z]+)_(\d{2}\.\d{2}\.\d{4})_([A-Za-z]+) ' . $processDate . ' ?([A-J]?)_/';

    // Find all files that match the pattern for the given process date
    $files = scandir($directory);
    $invoiceGroups = [];

    foreach ($files as $file) {
        if (preg_match($pattern, $file, $matches)) {
            $supplierName = $matches[1];
            $processDatePart = $matches[2];
            $optionalLetter = $matches[4];
            $groupKey = $supplierName . ' ' . $processDate . ' ' . $optionalLetter;
            // $groupKey = $supplierName . ' ' . $processDatePart . ' ' . $optionalLetter;

            // Group files by the key
            $invoiceGroups[$groupKey][] = $directory . '/' . $file;
        }
    }

    // Print $invoiceGroups for debugging
    echo '<pre>';
    print_r($invoiceGroups);
    echo '</pre>';

    // Create the invoice list spreadsheet
    $invoiceList = new Spreadsheet();
    $invoiceSheet = $invoiceList->getActiveSheet();

    // Set headers
    foreach ($headersArray as $colIndex => $header) {
        $columnLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
        $invoiceSheet->setCellValue($columnLetter . '1', $header);
    }

    $row = 2;

    // Process each group of files (each supplier's invoices)
    foreach ($invoiceGroups as $groupKey => $groupFiles) {
        list($supplierName, $rest) = explode(' ', $groupKey, 2); //list($supplierName, $invoiceCreationDate) = explode(' ', $groupKey, 2);

        list($invoiceCreationDate, $optionalLetter) = explode(' ', $rest . ' ', 2);

        // Get the oldest file in the group
        $oldestFile = getOldestFile($groupFiles);

        if ($oldestFile) {
            // Load the oldest file
            $spreadsheet = IOFactory::load($oldestFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Extract invoice data
            $invoiceNum = $sheet->getCell('B1')->getValue();
            $invoiceSum = $sheet->getCell('B4')->getValue();
            $invoiceCreationDate = $sheet->getCell('B3')->getValue();
            $remark = $sheet->getCell('B5')->getValue();
            if (!empty($optionalLetter)) {
                $remark = $remark.' '.$optionalLetter;  // Append the optional letter to remark if it's present
            } 
            
            

            // Get supplierCode from the supplierParam array
            // Initialize supplier code as null
            $supplierCode = null;
            // Loop through each element in the supplierParam array
            foreach ($supplierParam as $supplierData) {
                // Check if the current sub-array has the supplier name as a key
                if (isset($supplierData[$supplierName])) {
                    $supplierCode = $supplierData[$supplierName][0];  // Access the supplier code
                    break;  // Stop looping once the supplier is found
                }
            }
            // Handle case if the supplier was not found
            if ($supplierCode === null) {
                echo "Supplier not found in the parameter array: " . $supplierName. "<br>";
            }

            // Fill in the row with extracted data
            $invoiceSheet->setCellValue('A' . $row, $supplierName);           // Supplier
            $invoiceSheet->setCellValue('B' . $row, $invoiceCreationDate);    // Date
            $invoiceSheet->setCellValue('C' . $row, $supplierCode);           // SupplierCode
            $invoiceSheet->setCellValueExplicit('D' . $row, $invoiceNum, DataType::TYPE_STRING);
            $invoiceSheet->setCellValue('E' . $row, $invoiceSum);             // InvoiceSum
            $invoiceSheet->setCellValue('F' . $row, $remark);                 // Remark

            $row++;
        }


        /*
        // Move the processed files to the Temp directory
        foreach ($groupFiles as $file) {
            rename($file, $tempDirectory . '/' . basename($file));
        } */
        
    }

    $highestColumn = $invoiceSheet->getHighestColumn();
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $invoiceSheet->getColumnDimension($col)->setAutoSize(true);
    }
        

    // Save the spreadsheet as XLSX
    $writer = new Xlsx($invoiceList);
    $xlsxFile = $outputDirectory . '/invoice_list_' . $processDate . '.xlsx';
    $writer->save($xlsxFile);

    echo "XLSX file generated: " . $xlsxFile. "<br>";

    return $invoiceGroups;
}

// Function to get the first created file in a group
function getOldestFile($files) {
    $oldestFile = null;
    $oldestTime = PHP_INT_MAX;
    
    foreach ($files as $file) {
        $fileCreationTime = filemtime($file);
        if ($fileCreationTime < $oldestTime) {
            $oldestTime = $fileCreationTime;
            $oldestFile = $file;
        }
    }
    return $oldestFile;
}

//prepare all the invoices to be uploaded to back office 
function prepareInvoicesForUpload($invoiceGroups, $BOoutputDirectory,$BOtype,$shopData) {
    // Ensure the output directory exists
    if (!file_exists($BOoutputDirectory)) {
        mkdir($BOoutputDirectory, 0777, true);
    }

    // Loop through each invoice group
    foreach ($invoiceGroups as $key => $files) {
        // Find the file with "_PL_" in its name and ending with "_RV.xlsx"
        $targetFile = null;
        foreach ($files as $file) {
            if (strpos($file, '_PL_') !== false && preg_match('/_RV\.xlsx$/', $file)) {
                $targetFile = $file;
                break;
            }
        }

        // If a matching file is found, process it
        if ($BOtype == "comax") {
            //use designated process for Comax - create the invocies with right headers and column for Comax
            prepareInvoiceForComax($targetFile,$BOoutputDirectory,$key);

        } elseif ($BOtype == "all4shops") {
            //get the all4shop headers array and mapping array 
            $all4ShopsInvoiceHeadersArray = $shopData['all4ShopsInvoiceHeadersArray']; //!!!!!!!!!!! NEED TO COMPLETE 
            $all4ShopsInvoiceMappingArray = $shopData['all4ShopsInvoiceMappingArray']; //!!!!!!!!!!! NEED TO COMPLETE 

            //use designated process for all4shops - create the invocies with right headers and column for all4shops
            prepareInvoiceForAll4Shops($targetFile,$BOoutputDirectory,$key,$all4ShopsInvoiceHeadersArray,$all4ShopsInvoiceMappingArray);

        } else {
            echo "Back Office type $BOtype is not valid type <br>";
        }

    }
}

//prepare the cost price change sheet by appending all cost change sheets for a given date 
function preparePriceChangeSheetForBOUpload($invoiceGroups, $BOoutputDirectory, $priceChangeHeaders,$changeType = 'cost',$BOtype,$shopData) {

    // Determine settings based on change type
    $fileSubstring = $changeType === 'sale' ? '_SALE_PRICE-CHANGE_' : '_COST-CHANGE_';
    $fileNamePrefix = $changeType === 'sale' ? 'sale_price_change' : 'cost_price_change';
    $messagePrefix = $changeType === 'sale' ? 'sale price changes' : 'cost changes';

    // Ensure the output directory exists
    if (!file_exists($BOoutputDirectory)) {
        mkdir($BOoutputDirectory, 0777, true);
    }

    // Step 1: Initialize the cost change sheet
    $costChangeSheet = new Spreadsheet();
    $costChangeWorksheet = $costChangeSheet->getActiveSheet();

    // Step 2: Add headers from $priceChangeHeaders
    foreach ($priceChangeHeaders as $colIndex => $header) {
        // Convert the column index to a column letter (1 => A, 2 => B, etc.)
        $columnLetter = Coordinate::stringFromColumnIndex($colIndex + 1);
        // Set the header value in the first row of each column
        $costChangeWorksheet->setCellValue($columnLetter . '1', $header);
    }

    // Step 3: Extract the process date from the first key in $invoiceGroups
    reset($invoiceGroups);  // Move internal pointer to the first item
    $firstKey = key($invoiceGroups);
    //debug
    //echo "firstKey $firstKey <br>";
    preg_match('/\d{2}-\d{2}-\d{2}/', $firstKey, $matches);
    $processDate = $matches[0];  // e.g., "25-10-24"

    // Step 4-7: Process each item in $invoiceGroups to find "_COST-CHANGE_" files and copy data
    $rowIndex = 2;  // Start appending from row 2
    foreach ($invoiceGroups as $key => $files) {
        $costChangeFile = null;
        
        // Look for the "_COST-CHANGE_" file within the group
        foreach ($files as $file) {
            if (strpos($file, $fileSubstring) !== false) {
                $costChangeFile = $file;
                break;
            }
        }

        // If a "_COST-CHANGE" or " _SALE_PRICE-CHANGE_" file was found, process it
        if ($costChangeFile) {
            // Load the file and get the active sheet
            $spreadsheet = IOFactory::load($costChangeFile);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();

            // Copy rows from row 2 to the last row in the found file
            for ($sourceRow = 2; $sourceRow <= $highestRow; $sourceRow++, $rowIndex++) {
                //run on the row over each column
                for ($col = 1; $col <= Coordinate::columnIndexFromString($highestColumn); $col++) {
                    // Convert column index to column letter
                    $columnLetter = Coordinate::stringFromColumnIndex($col);
                    //get the value from the source file
                    $value = $worksheet->getCell($columnLetter . $sourceRow)->getValue();
                    //set the value in the cost chage worksheet
                    $costChangeWorksheet->setCellValue($columnLetter . $rowIndex, $value);
                }

            }

            // Step 6: Output message after each copy
            echo "$key $messagePrefix were copied<br>";
        } else {
            echo "No '$fileSubstring' file found for $key<br>";
        }
    }



    // Loop from row 2 to the last row in Column 1 and correct the ID sequence 
    $highestRow = $costChangeWorksheet->getHighestRow();
    for ($row = 2; $row <= $highestRow; $row++) {
        $costChangeWorksheet->setCellValue('A' . $row, $row - 1);  // Set sequential values starting from 1
    }

    // Step 7: Save the sheet as Xlsx sheet that is general + comax complay ($BOtype = comax)

    //define column B as string type as comax is in xlsx that keep this data type
    //define columns E and F as percentage  type with 2 digist after the decimal point
    for ($row = 2; $row <= $highestRow; $row++) {
        $cell = $costChangeWorksheet->getCell('B' . $row); // Get the cell in column B for the current row
        $value = $cell->getValue(); // Get the current value of the cell
        $cell->setValueExplicit((string)$value, DataType::TYPE_STRING); //set the value as string

        //round the sale price to 1 decimal point 

        // Column F,G,H,I: Set value as percentage with 2 decimal places
        $costChangeWorksheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $costChangeWorksheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $costChangeWorksheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
        $costChangeWorksheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
    }

    //autosize Columns B to L 
    foreach (range('B', 'L') as $columnID) {
        $costChangeWorksheet->getColumnDimension($columnID)->setAutoSize(true);
    }


    //save the price chnage file as XLSX
    $xlsxFileName = $BOoutputDirectory . "/$fileNamePrefix $processDate.xlsx";
    $writer = new Xlsx($costChangeSheet);
    $writer->save($xlsxFileName);

    echo "XLSX file for new prices was generated: " . $xlsxFileName."<br>";

    //if the ERP is all4shops then it will also generate all4shops compatible file 
    if ($BOtype == "all4shops") {
        //get the header array and mapping for pricechange for all4shops 
        $all4ShopsPriceChangeHeadersArray  = $shopData['all4ShopsPriceChangeHeadersArray'];
        $all4ShopsPriceChangeMappingArray = $shopData['all4ShopsPriceChangeMappingArray'];

        //map from the pricechange sheet to all4shops compatble sheet 
        $all4ShopsPriceChangeSpreadsheet = copyColumnsAndHeadersToNewSpreadsheet($costChangeSheet, $all4ShopsPriceChangeHeadersArray, $all4ShopsPriceChangeMappingArray);

        //if the back office type is all4shops save as CSV
        $csvFileName = $BOoutputDirectory . "/$fileNamePrefix $processDate all4shops.csv";
        $writer = new Csv($all4ShopsPriceChangeSpreadsheet );
        $writer->save($csvFileName);
    
        echo "CSV file for new prices was generated: " . $csvFileName."<br>";

    } 

}

//the function takes the processed invoice xlsx sheet and convert to type that can be upload to All4Shops back office
function prepareInvoiceForAll4Shops($targetFile,$BOoutputDirectory,$key,$all4ShopsInvoiceHeadersArray,$all4ShopsInvoiceMappingArray) {

        // If a matching file is found, process it
        if ($targetFile) {
            // Load the spreadsheet
            $spreadsheet = IOFactory::load($targetFile);

            //get the header array , and mapping array 
            $invoiceSpreadsheet = copyColumnsAndHeadersToNewSpreadsheet($spreadsheet, $all4ShopsInvoiceHeadersArray, $all4ShopsInvoiceMappingArray);
       
            // Define the CSV file name based on the key
            $csvFileName = $BOoutputDirectory . '/' . $key . ' toUpload.csv';

            // Save the new spreadsheet as CSV
            $writer = new Csv($invoiceSpreadsheet);
            $writer->save($csvFileName);

            echo "BO file (CSV) for invoice upload created: " . $csvFileName . "<br>";
        } else {
            echo "No matching file found for group: " . $key . "<br>";
        }

}

//the function takes the processed invoice xlsx sheet and convert to type that can be upload to Comax back office
function prepareInvoiceForComax($targetFile,$BOoutputDirectory,$key) {

            // If a matching file is found, process it
            if ($targetFile) {
                // Load the spreadsheet
                $spreadsheet = IOFactory::load($targetFile);
                $worksheet = $spreadsheet->getActiveSheet();
    
                // Create a new spreadsheet for the CSV output
                $xlsxSpreadsheet = new Spreadsheet();
                $xlsxSheet = $xlsxSpreadsheet->getActiveSheet();


                //copy the first row from A1 to G1
                
                $range = 'A1:G1';
                $data = $worksheet->rangeToArray($range, null, true, true, true); // Get data as array
                $xlsxSheet->fromArray($data, null, 'A1'); // Paste data into target range
                
                /*
                $range = 'A1:G1';
                $data = $worksheet->rangeToArray($range, '', false, false, true); // Extract data with correct parameters

                $spreadsheet = new Spreadsheet();
                $xlsxSheet = $spreadsheet->getActiveSheet();
                $xlsxSheet->fromArray($data, null, 'A1'); // Paste data
                */
                // Copy only columns A, B, D, G to the new sheet
                $highestRow = $worksheet->getHighestRow();
                for ($row = 2; $row <= $highestRow; $row++) {
                    // Copy column A
                    $xlsxSheet->setCellValue('A' . $row, $worksheet->getCell('A' . $row)->getValue());
                    
                    // Copy column B and set it as string data type
                    $valueB = $worksheet->getCell('B' . $row)->getValue();
                    $xlsxSheet->setCellValueExplicit('B' . $row, $valueB, DataType::TYPE_STRING);

                    
                    // Copy columns C and D
                    $xlsxSheet->setCellValue('D' . $row, $worksheet->getCell('D' . $row)->getValue());
                    $xlsxSheet->setCellValue('G' . $row, $worksheet->getCell('G' . $row)->getValue());
                }
                
                // Define the xlsx file name based on the key
                $xlsxFileName = $BOoutputDirectory . '/' . $key . ' toUpload.xlsx';
    
                // Save the new spreadsheet as Xlsx
                $writer = new xlsx($xlsxSpreadsheet);
                $writer->save($xlsxFileName);
    
                echo "BO file (XLSX) for invoice upload created: " . $xlsxFileName . "<br>";
            } else {
                echo "No matching file found for group: " . $key . "<br>";
            }

}


function prepareNewProductsSheetForBOUpload($invoiceGroups, $BOoutputDirectory, $newProductsBOHeaders, $processDate, $supplierColumn) {
    // Step 1: Create new spreadsheet
    $newProductsSheetForBO = new Spreadsheet();
    $sheet = $newProductsSheetForBO->getActiveSheet();

    //get the supplier config file 
    $supplierConfigObject = uploadJSONdata('../suppliers.json');


    // Step 2: Set headers in the first row
    foreach ($newProductsBOHeaders as $index => $header) {
        $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
        $sheet->getCell($columnLetter . '1')->setValue($header);
    }

    // Step 3: Find files containing "_NEW-PRODUCTS_" and store them in $newProductsFilesArray
    $newProductsFilesArray = [];
    foreach ($invoiceGroups as $groupFiles) {
        foreach ($groupFiles as $filePath) {
            if (strpos(basename($filePath), "_NEW-PRODUCTS_") !== false) {
                $newProductsFilesArray[] = $filePath;
            }
        }
    }

    // Step 4: Generate $newProductsSupplierNameArray
    $newProductsSupplierNameArray = [];
    foreach ($newProductsFilesArray as $filePath) {
        $baseName = basename($filePath);
        $supplierName = explode('_', $baseName)[0];
        $newProductsSupplierNameArray[] = $supplierName;
    }

    // Step 5: Copy rows from each file in $newProductsFilesArray to $newProductsSheetForBO
    $currentRow = 2; // Start from the second row (first row is for headers)
    foreach ($newProductsFilesArray as $key => $filePath) {
        //$supplierName = $newProductsSupplierNameArray[$key];
        //$supplierObject = getSupplierData($supplierConfigObject, $supplierName);
        //$supplierHebrewName = $supplierObject['hebrewName'];

        // Load the file
        $spreadsheet = IOFactory::load($filePath);
        $sourceSheet = $spreadsheet->getActiveSheet();

        // Get the highest row and column
        $highestRow = $sourceSheet->getHighestRow();
        $highestColumn = $sourceSheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Iterate through the rows in the source sheet
        for ($row = 2; $row <= $highestRow; $row++) { // Skip header row
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $columnLetter = Coordinate::stringFromColumnIndex($col);
                $cellValue = $sourceSheet->getCell($columnLetter.$row)->getValue();
                $sheet->getCell($columnLetter.$currentRow)->setValue($cellValue);
            }
            // Add the supplier name to the designated column
            //$supplierColumnLetter = Coordinate::stringFromColumnIndex($supplierColumn);
            //$sheet->getCell($supplierColumnLetter.$currentRow)->setValue($supplierHebrewName); 
            //change the data type for columns that holds the barcode : column A and C
            $sheet->getCell("A".$currentRow)->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $sheet->getCell("C".$currentRow)->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
            $currentRow++;
        }
    }

    // Set the active sheet to right-to-left layout
    $newProductsSheetForBO->getActiveSheet()->setRightToLeft(true);
    //autofit columns A, C, I
    $newProductsSheetForBO->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
    $newProductsSheetForBO->getActiveSheet()->getColumnDimension('C')->setAutoSize(true);
    $newProductsSheetForBO->getActiveSheet()->getColumnDimension('I')->setAutoSize(true);


    // Step 6: Save the spreadsheet as an XLSX file
    $outputFileName = "New Products BO toUpload " . $processDate . ".xlsx";
    $outputFilePath = rtrim($BOoutputDirectory, '/') . '/' . $outputFileName;
    $writer = IOFactory::createWriter($newProductsSheetForBO, 'Xlsx');
    $writer->save($outputFilePath);
    echo "Upload file for new products was generated: $outputFilePath <br>";

    return $outputFilePath;
}

function emailBOResultsToCustomer($shopData) {

    //get the indicator $toSend 
    $toSend = $shopData['toSendToCustomer'];
    //decide if to create an email based on the $toSend parameter
    if ($toSend != 0) {


        //get the address destination of the customer 
        $customerDestinationEmailAddress = $shopData["customerDestinationEmailAddress"];
        //get the BO directory path 
        $BOdirectoryPath = $shopData["BOoutputDirectory"];

        //get the number of invoices , number of new products and number of price changes recommnedations
        //get full paht to "sale_price_change" file, "New Products BO toUpload" file and "invoice_list" file 
        //******************function from BO directory ***********************
        $BOResultsParameters = getBOResultsParameters($BOdirectoryPath);

        //file path parameters
        $pathTofile_invoice_list = $BOResultsParameters['pathTofile_invoice_list'];
        $pathTofile_new_products = $BOResultsParameters['pathTofile_new_products'];
        $pathTofile_price_change = $BOResultsParameters['pathTofile_price_change'];

        //numeric parameters 
        $invoicesNum = $BOResultsParameters['invoicesNum'];
        $newProductsNum = $BOResultsParameters['newProductsNum'];
        $priceChangeNum = $BOResultsParameters['priceChangeNum'];

        //set the email body 
        $emailBodyText = buildBOResultsEmailMessage($invoicesNum,$newProductsNum,$priceChangeNum,$shopData);

        //get time stamp 
        date_default_timezone_set('Asia/Jerusalem'); // Set timezone
        $formatter = new IntlDateFormatter(
            'he_IL', // Hebrew locale
            IntlDateFormatter::FULL, // Full date style
            IntlDateFormatter::SHORT // Short time style
        );
        $formatter->setPattern("EEEE d MMMM 'שעה' HH:mm"); // Custom pattern
        $date = $formatter->format(new DateTime());

        //set the email subject 
        $emailSubject = "$date - דוח קליטת תעודות כניסה ובקרת רווחיות";



        //create email or send / draft based on $toSend value
        addDraftEmailWithProcessedFiles($pathTofile_invoice_list, $pathTofile_new_products,$pathTofile_price_change, $emailBodyText, $customerDestinationEmailAddress, $emailSubject,$toSend);

    } else {
        //do not generate email 
        echo "Email was not generated based on toSend value: $toSend <br>";

    }

}

//the function will get access to the toBackOffice directory and get parameters and files to send to customer 

function getBOResultsParameters($BOdirectoryPath) {
    $BOResultsParameters = [];

    // Helper function to find the newest file based on modified time
    function getNewestFile($files) {
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        return $files[0];
    }

    // Function to count populated rows in a CSV file (excluding the header row)
    function countPopulatedRows($filePath) {
        $rowCount = 0;
        if (($handle = fopen($filePath, 'r')) !== false) {
            // Skip the first row (header)
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if (array_filter($row)) { // Check if the row is not empty
                    $rowCount++;
                }
            }
            fclose($handle);
        }
        return $rowCount;
    }

    // Handle "invoice_list" file
    $invoiceListFiles = glob($BOdirectoryPath . DIRECTORY_SEPARATOR . 'invoice_list*');
    if (empty($invoiceListFiles)) {
        echo "<script>alert('Invoice list file was not found');</script>";
        return null;
    } elseif (count($invoiceListFiles) > 1) {
        echo "<script>alert('More than one invoice list was found');</script>";
        $pathTofile_invoice_list = getNewestFile($invoiceListFiles);
    } else {
        $pathTofile_invoice_list = $invoiceListFiles[0];
    }
    $BOResultsParameters['pathTofile_invoice_list'] = $pathTofile_invoice_list;
    $BOResultsParameters['invoicesNum'] = countPopulatedRows($pathTofile_invoice_list);

    // Handle "New Products" file
    $newProductsFiles = glob($BOdirectoryPath . DIRECTORY_SEPARATOR . 'New Products*');
    if (empty($newProductsFiles)) {
        echo "New products file was not found";
        return null;
    } elseif (count($newProductsFiles) > 1) {
        echo "<script>alert('More than one new products file was found');</script>";
        $pathTofile_new_products = getNewestFile($newProductsFiles);
    } else {
        $pathTofile_new_products = $newProductsFiles[0];
    }
    $BOResultsParameters['pathTofile_new_products'] = $pathTofile_new_products;
    $BOResultsParameters['newProductsNum'] = countPopulatedRows($pathTofile_new_products);

    // Handle "sale_price_change" file
    $priceChangeFiles = glob($BOdirectoryPath . DIRECTORY_SEPARATOR . 'sale_price_change*');
    if (empty($priceChangeFiles)) {
        echo "New prices file was not found";
        return null;
    } elseif (count($priceChangeFiles) > 1) {
        echo "<script>alert('More than one price change file was found');</script>";
        $pathTofile_price_change = getNewestFile($priceChangeFiles);
    } else {
        $pathTofile_price_change = $priceChangeFiles[0];
    }
    $BOResultsParameters['pathTofile_price_change'] = $pathTofile_price_change;
    $BOResultsParameters['priceChangeNum'] = countPopulatedRows($pathTofile_price_change);

    return $BOResultsParameters;
}



?>