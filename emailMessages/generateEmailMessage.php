<?php

function createEmailComaxFiles($OCRSanitySpreadsheet,$supplierData,$shopData) { //,$shopData 

    //get the active sheet
    $sheet = $OCRSanitySpreadsheet->getActiveSheet();

    //get supplier name 
    $supplier = $sheet->getCell([2,2])->getValue(); 
    //get the invoice date 
    $dateVal = $sheet->getCell([2,3])->getValue(); 
    //get the invocie sum 
    //$invoiceSum = $sheet->getCell([2,4])->getValue();

    //echo "InvoiceSum: $invoiceSum";

    //calculate the excel sum (Qty*price for all lines ) to get $calculatedSum
    $lastUsedRow = $sheet->getHighestRow('A');
    $calculatedSum = 0;

    //calculate the sum basd on checksum functions 
    $checkSumMethod = $supplierData['OCRsanityMethod'];

    //in case that the checksum method is not defined 
    //in case the name is not defined 
    if (!$checkSumMethod) {
        echo "The checkSum method of '$supplier' was not found in the config file.<br>";
    }

    //set up the sumPair
    //initiate the sumPair
    $sumPair = [0,0];

    //disable echo for this code part
    ob_start();

// Code where you want to disable echo
// Echo statements in this section won't output to the browser
echo "This won't be echoed.";

            // End output buffering and discard the output
            
    if ($checkSumMethod == 'Discount2') {
        //Calculate the sum according to 'Discount2' checkSum
        $sumPair = checkSumOCRsanityDiscount2($OCRSanitySpreadsheet);
    } elseif ($checkSumMethod == 'LineTotal') {
        //Calculate the sum according to 'LineTotal' checkSum
        $sumPair = checkSumOCRsanityLineTotal($OCRSanitySpreadsheet);
    } elseif ($checkSumMethod == 'FlatDiscountAndSV') { 
        //Calculate the sum according to 'FlatDiscountAndSV' checkSum
        $sumPair = checkSumOCRsanityNoLineTotal($OCRSanitySpreadsheet,$shopData);
    } elseif ($checkSumMethod == 'Simple') {
        //Calculate the sum according to 'Simple' checkSum
        $sumPair = checkSumOCRsanitySimple($OCRSanitySpreadsheet);
    } else {
        //if the method not found by the suppiler name 
        $sumPair = null;
        
    }

    ob_end_clean();

    $invsum = $sumPair[0]; 
    $calcsum = $sumPair[1];



    if ($calculatedSum == null) {
        echo "For email text purpose: The CheckSum method for the supplier $supplier was not found <br>";
        $calculatedSum = "Calculated Sum not found<br>";

    }
    


    /*
    for ($rowIndex = 7; $rowIndex <= $lastUsedRow; $rowIndex++) {

        //get the price and qty from the OCR sheet
        $priceItem = $sheet->getcell([1,$rowIndex])->getValue();
        $qtyItem = $sheet->getcell([2,$rowIndex])->getValue();

        $calculatedSum = $calculatedSum + $priceItem*$qtyItem;

    } */

    //get the  name of the shop adminstrator from the shop form 
    $name = $shopData["administratorName"];

    //echo the message to the screen
    $supplierName = $supplierData['hebrewName'];

    //in case the name is not defined 
    if (!$supplierName) {
        echo "The  Hebrew name of '$supplier' was not found in the config file.<br>";
        $supplierName =  "שם_םפק_גנר";
    }

    buildOCREmailMessage($name,$supplierName,$dateVal,$invsum,$calcsum);

}

function buildOCREmailMessage($name,$supplierName,$dateVal,$invoiceSum,$calculatedSum) {

    // Read the content of the file
    $message = file_get_contents('../emailMessages/old_email_message.txt');

    // Replace placeholders with variable values
    $message = str_replace('name', $name, $message);
    $message = str_replace('supplierName', $supplierName, $message);
    $message = str_replace('Date', $dateVal, $message);
    $message = str_replace('invoiceSum', $invoiceSum, $message);
    $message = str_replace('calculatedSum', $calculatedSum, $message);

    // Output the modified message
    echo nl2br($message);
    echo "<br>";

    //return the message
    return $message;

}

function buildOCREmailsubject($shop, $supplier,$invoiceDate,$invoiceNumber) {

    $message = "OCR files message: Shop: ".$shop." Supplier: ".$supplier." invoice Date: ".$invoiceDate." invoice number: ".$invoiceNumber;

    return $message;

}

function buildBOResultsEmailMessage($invoicesNum,$newProductsNum,$priceChangeNum,$shopData) {

    //get message file 
    $BOEmailMessageTxtFile = $shopData["BOEmailMessageTxtFile"];

    //get customer administrator name 
    $name = $shopData["administratorName"];

    // Read the content of the file
    $message = file_get_contents($BOEmailMessageTxtFile);

    // Replace placeholders with variable values
    $message = str_replace('name', $name, $message);
    $message = str_replace('invoicesNum', $invoicesNum, $message);
    $message = str_replace('newProductsNum', $newProductsNum, $message);
    $message = str_replace('priceChangeNum', $priceChangeNum, $message);


    // Output the modified message
    echo nl2br($message);
    echo "<br>";

    //return the message
    return $message;

}

?>