
<?php

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;


//Check if the total of the OCRsanity invoice is equal to the sum of all the lines
function checkSumOCRsanitySimple($OCRsanitySpreadSheet) {

    //get the total sum of the invoice
    $OCRsanitySheet = $OCRsanitySpreadSheet->getActiveSheet();
    $totalInvoiceSum = $OCRsanitySheet->getCell([2,4])->getValue();

    //iterate through the Quantaty and Price and accumulate the sum
    $CalculatedSum = 0;

    //set the indication if all numeric items are actually numeric
    $AreAllItemsNumeric = true;

    //get the last row 
    $lastUsedRow = $OCRsanitySheet->getHighestRow('A');



    for ($rowIndex = 7; $rowIndex <= $lastUsedRow; $rowIndex++) {

        //get the Price and Qty value
        $ItemPrice = $OCRsanitySheet->getCell([1,$rowIndex])->getvalue();
        $ItemQty = $OCRsanitySheet->getCell([2,$rowIndex])->getvalue();

        //actual invocie row (for echo purposes)
        $actualInvoiceRow = $rowIndex-6;

        // If the price value is not numeric
        if (!is_numeric($ItemPrice)) {
            //echo for stoping the process 
            echo "The Price value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreAllItemsNumeric = false;
        } 

        // If the Qty value is not numeric
        if (!is_numeric($ItemQty)) {
            //echo for stoping the process 
            echo "The Quantity value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreAllItemsNumeric = false;
        }

        

        //if values in the row are numeric - add up the price*Qty of each line
        if (is_numeric($ItemQty) and is_numeric($ItemPrice)) {
            $CalculatedSum = $CalculatedSum + $ItemPrice*$ItemQty;
        } else {
            //do nothing - do not include the line in calculation 
        }
        
    }

    // in case $totalInvoiceSum is not numeric  it change to 0 (zero) and echo 
    if (!is_numeric($totalInvoiceSum)) {
        echo "Total Invoice Sum from OCR is $totalInvoiceSum and not numeric so it have been changed to 0 <br>";
        $totalInvoiceSum = 0;

    } 

    //if all items numerica  - compare the total vs the calculated and echo accordingly 
    if ($AreAllItemsNumeric) {
        echo "Invoice sum is: $totalInvoiceSum <br>";
        echo "Calculated sum is: $CalculatedSum <br>";
        if ($CalculatedSum > ($totalInvoiceSum + 5) or  $CalculatedSum < ($totalInvoiceSum - 5)) { 
            echo "Difference is higher than 5 - NOT OK <br>";
        } else {
            echo "Difference is lower than 5 - OK <br>";
    }
    } else{
        //if there is atlease one non-numeric value then declaire the calculation can not be done
        echo "Not all items in OCRsanity sheet are numeric - THE PROCESS CAN NOT BE COMPLETED <br>";
    }

    //return a pair of Invoice sum [0] and calculated Sum [1]
    return [$totalInvoiceSum,$CalculatedSum];
    
}

//Check if the total of the OCRsanity invoice is equal to the sum of all the lines
function checkSumOCRsanityLineTotal($OCRsanitySpreadSheet) {

    //get the total sum of the invoice
    $OCRsanitySheet = $OCRsanitySpreadSheet->getActiveSheet();
    $totalInvoiceSum = $OCRsanitySheet->getCell([2,4])->getValue();

    //iterate through the Quantaty and Price and accumulate the sum
    $CalculatedSum = 0;

    //set the indication if all numeric items are actually numeric
    $ArePriceQtyItemsNumeric = true;
    //$AreLineTotalItemsNumeric = true;

    //get the last row 
    $lastUsedRow = $OCRsanitySheet->getHighestRow('A');

    for ($rowIndex = 7; $rowIndex <= $lastUsedRow; $rowIndex++) {

        //get the Price and Qty and LineTotal value
        $ItemPrice = $OCRsanitySheet->getCell([1,$rowIndex])->getvalue();
        $ItemQty = $OCRsanitySheet->getCell([2,$rowIndex])->getvalue();
        $ItemLineTotal = $OCRsanitySheet->getCell([4,$rowIndex])->getvalue();


        //actual invocie row (for echo purposes)
        $actualInvoiceRow = $rowIndex-6;

        // If the price value is not numeric
        if (!is_numeric($ItemPrice)) {
            //echo for stoping the process 
            echo "The Price value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $ArePriceQtyItemsNumeric = false;
        } 

        // If the Qty value is not numeric
        if (!is_numeric($ItemQty)) {
            //echo for stoping the process 
            echo "The Quantity value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $ArePriceQtyItemsNumeric = false;
        }

        // If the LineTotal value is not numeric
        if (!is_numeric($ItemLineTotal)) {
            //echo for stoping the process 
            echo "The LineTotal value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([4,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([4,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            //$AreLineTotalItemsNumeric = false;
        }

        //If  the line values are numeric check LineTotal correctness 
        if (is_numeric($ItemPrice) and is_numeric($ItemQty) and is_numeric($ItemLineTotal)) {
            //calculate the LineTotal
            $CalculatedTotal =  $ItemPrice*$ItemQty;
            if (abs($CalculatedTotal-$ItemLineTotal) < 0.3) {
                    //do nothing 
            } else {
                    //line total differet than calculated lineTotal:
                    $TotalDiff = abs($CalculatedTotal-$ItemLineTotal);
                    echo "Line Total in row $actualInvoiceRow is different in $TotalDiff > 0.3 ILS <br>";
                    //set the LineTotal with a frame
                    $OCRsanitySheet->getStyle([4,$rowIndex])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);

            }
        }

        

        //if values in the row are numerica - add up the price*Qty of each line
        if (is_numeric($ItemPrice) and is_numeric($ItemQty)) {
            $CalculatedSum = $CalculatedSum + $ItemPrice*$ItemQty;
        } else {
            //do nothing - do not include the line in calculation 
        }
        
        

    }

    // in case $totalInvoiceSum is not numeric  it change to 0 (zero) and echo 
    if (!is_numeric($totalInvoiceSum)) {
        echo "Total Invoice Sum from OCR is $totalInvoiceSum and not numeric so it have been changed to 0 <br>";
        $totalInvoiceSum = 0;

    } 

    //if all items numeric  - compare the total vs the calculated and echo accordingly 
    if ($ArePriceQtyItemsNumeric) {
        echo "Invoice sum is: $totalInvoiceSum <br>";
        echo "Calculated sum is: $CalculatedSum <br>";
        if ($CalculatedSum > ($totalInvoiceSum + 5) or  $CalculatedSum < ($totalInvoiceSum - 5)) { 
            echo "Difference is higher than 5 - NOT OK <br>";
        } else {
            echo "Difference is lower than 5 - OK <br>";
    }
    } else{
        //if there is atlease one non-numeric value then declaire the calculation can not be done
        echo "Not all items in OCRsanity sheet are numeric - THE PROCESS CAN NOT BE COMPLETED <br>";
    }

    //return a pair of Invoice sum [0] and calculated Sum [1]
    return [$totalInvoiceSum,$CalculatedSum];
    
}

//check for each line if LineTotal==((Price/(1+Discount1/100))/(1+Discount2/100))*Qty
// AND if invocie total = calculated total
function checkSumOCRsanityDiscount2($OCRsanitySpreadSheet) {

    //get the total sum of the invoice
    $OCRsanitySheet = $OCRsanitySpreadSheet->getActiveSheet();
    $totalInvoiceSum = $OCRsanitySheet->getCell([2,4])->getValue();

    //iterate through the Quantaty and Price and accumulate the sum
    $CalculatedSum = 0;

    //set the indication if all numeric items (Price, Qty, LineTotal, Discount1, Discount2) are actually numeric
    $AreItemsNumeric = true;

    //get the last row 
    $lastUsedRow = $OCRsanitySheet->getHighestRow('A');

    for ($rowIndex = 7; $rowIndex <= $lastUsedRow; $rowIndex++) {

        //get the Price and Qty and LineTotal value
        $ItemPrice = $OCRsanitySheet->getCell([1,$rowIndex])->getvalue();
        $ItemQty = $OCRsanitySheet->getCell([2,$rowIndex])->getvalue();
        $ItemLineTotal = $OCRsanitySheet->getCell([4,$rowIndex])->getvalue();
        $ItemDiscount1 = $OCRsanitySheet->getCell([5,$rowIndex])->getvalue();
        $ItemDiscount2 = $OCRsanitySheet->getCell([6,$rowIndex])->getvalue();


        //actual invocie row (for echo purposes)
        $actualInvoiceRow = $rowIndex-6;

        // If the price value is not numeric
        if (!is_numeric($ItemPrice)) {
            //echo for stoping the process 
            echo "The Price value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        } 

        // If the Qty value is not numeric
        if (!is_numeric($ItemQty)) {
            //echo for stoping the process 
            echo "The Quantity value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        }

        // If the LineTotal value is not numeric
        if (!is_numeric($ItemLineTotal)) {
            //echo for stoping the process 
            echo "The LineTotal value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([4,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([4,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        }

        // If the Discount1 value is not numeric
        if (!is_numeric($ItemDiscount1)) {
            //echo for stoping the process 
            echo "The Discount1 value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([5,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([5,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        }

        // If the Discount2 value is not numeric
        if (!is_numeric($ItemDiscount2)) {
            //echo for stoping the process 
            echo "The Discount2 value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([6,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([6,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        }

        //If  the line values are numeric check LineTotal correctness 
        if (is_numeric($ItemPrice) and
             is_numeric($ItemQty) and
              is_numeric($ItemLineTotal) and
               is_numeric($ItemDiscount1) and
                is_numeric($ItemDiscount2)) {
            //calculate the LineTotal - ((Price/(1+Discount1/100))/(1+Discount2/100))*Qty
            $CalculatedTotal =  (($ItemPrice/(1+$ItemDiscount1/100))/(1+$ItemDiscount2/100))*$ItemQty ;
            if (abs($CalculatedTotal-$ItemLineTotal) < 0.3) {
                    //do nothing 
            } else {
                    //line total differet than calculated lineTotal:
                    $TotalDiff = abs($CalculatedTotal-$ItemLineTotal);
                    echo "Line Total in row $actualInvoiceRow is different in $TotalDiff > 0.3 ILS <br>";
                    //set the LineTotal with a frame
                    $OCRsanitySheet->getStyle([4,$rowIndex])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);

            }
        }

        

        //if values in the row are numerica - add up the price*Qty of each line
        if (is_numeric($ItemPrice) and is_numeric($ItemQty) and is_numeric($ItemDiscount1) and is_numeric($ItemDiscount2)) {
            $CalculatedSum = $CalculatedSum + (($ItemPrice/(1+$ItemDiscount1/100))/(1+$ItemDiscount2/100))*$ItemQty;
        } else {
            //do nothing - do not include the line in calculation 
        }
        
        

    }

    // in case $totalInvoiceSum is not numeric  it change to 0 (zero) and echo 
    if (!is_numeric($totalInvoiceSum)) {
        echo "Total Invoice Sum from OCR is $totalInvoiceSum and not numeric so it have been changed to 0 <br>";
        $totalInvoiceSum = 0;

    } 

    //if all items numeric  - compare the total vs the calculated and echo accordingly 
    if ($AreItemsNumeric) {
        echo "Invoice sum is: $totalInvoiceSum <br>";
        echo "Calculated sum is: $CalculatedSum <br>";
        if ($CalculatedSum > ($totalInvoiceSum + 5) or  $CalculatedSum < ($totalInvoiceSum - 5)) { 
            echo "Difference is higher than 5 - NOT OK <br>";
        } else {
            echo "Difference is lower than 5 - OK <br>";
    }
    } else{
        //if there is atlease one non-numeric value then declaire the calculation can not be done
        echo "Not all items in OCRsanity sheet are numeric - THE PROCESS CAN NOT BE COMPLETED <br>";
    }

    //return a pair of Invoice sum [0] and calculated Sum [1]
    return [$totalInvoiceSum,$CalculatedSum];
}

function checkSumOCRsanityDiscount1($OCRsanitySpreadSheet) { 
    // !!!!!! DIFFRENT THAN DISCOUNT 2: 
    // Discount1: Price*(1-Discount1/100)  
    // Discount2: (Price/(1+Discount1/100))/(1+Discount2/100)

    //get the total sum of the invoice
    $OCRsanitySheet = $OCRsanitySpreadSheet->getActiveSheet();
    $totalInvoiceSum = $OCRsanitySheet->getCell([2,4])->getValue();

    //iterate through the Quantaty and Price and accumulate the sum
    $CalculatedSum = 0;

    //set the indication if all numeric items (Price, Qty, LineTotal, Discount1, Discount2) are actually numeric
    $AreItemsNumeric = true;

    //get the last row 
    $lastUsedRow = $OCRsanitySheet->getHighestRow('A');

    for ($rowIndex = 7; $rowIndex <= $lastUsedRow; $rowIndex++) {

        //get the Price and Qty and LineTotal value
        $ItemPrice = $OCRsanitySheet->getCell([1,$rowIndex])->getvalue();
        $ItemQty = $OCRsanitySheet->getCell([2,$rowIndex])->getvalue();
        $ItemLineTotal = $OCRsanitySheet->getCell([4,$rowIndex])->getvalue();
        $ItemDiscount1 = $OCRsanitySheet->getCell([5,$rowIndex])->getvalue();
        //$ItemDiscount2 = $OCRsanitySheet->getCell([6,$rowIndex])->getvalue();


        //actual invocie row (for echo purposes)
        $actualInvoiceRow = $rowIndex-6;

        // If the price value is not numeric
        if (!is_numeric($ItemPrice)) {
            //echo for stoping the process 
            echo "The Price value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        } 

        // If the Qty value is not numeric
        if (!is_numeric($ItemQty)) {
            //echo for stoping the process 
            echo "The Quantity value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        }

        // If the LineTotal value is not numeric
        if (!is_numeric($ItemLineTotal)) {
            //echo for stoping the process 
            echo "The LineTotal value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([4,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([4,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        }

        // If the Discount1 value is not numeric
        if (!is_numeric($ItemDiscount1)) {
            //echo for stoping the process 
            echo "The Discount1 value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([5,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([5,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreItemsNumeric = false;
        }

        //If  the line values are numeric check LineTotal correctness 
        if (is_numeric($ItemPrice) and
             is_numeric($ItemQty) and
              is_numeric($ItemLineTotal) and
               is_numeric($ItemDiscount1)) { // and is_numeric($ItemDiscount2)
            //calculate the LineTotal - ((Price/(1+Discount1/100))/(1+Discount2/100))*Qty
            $CalculatedTotal =  ($ItemPrice*(1-$ItemDiscount1/100))*$ItemQty ; // ($ItemPrice/(1+$ItemDiscount1/100))*$ItemQty
            echo "ItemPrice $ItemPrice <br>";
            echo "ItemQty $ItemQty <br>";
            echo "ItemLineTotal $ItemLineTotal <br>";
            echo "ItemDiscount1 $ItemDiscount1 <br>";
            echo "CalculatedTotal $CalculatedTotal <br>";
            if (abs($CalculatedTotal-$ItemLineTotal) < 0.3) {
                    //do nothing 
            } else {
                    //line total differet than calculated lineTotal:
                    $TotalDiff = abs($CalculatedTotal-$ItemLineTotal);
                    echo "Line Total in row $actualInvoiceRow is different in $TotalDiff > 0.3 ILS <br>";
                    //set the LineTotal with a frame
                    $OCRsanitySheet->getStyle([4,$rowIndex])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_MEDIUM);

            }
        }

        

        //if values in the row are numerica - add up the price*Qty of each line
        if (is_numeric($ItemPrice) and is_numeric($ItemQty) and is_numeric($ItemDiscount1)) { // and is_numeric($ItemDiscount2)
            $CalculatedSum = $CalculatedSum + ($ItemPrice*(1-$ItemDiscount1/100))*$ItemQty; //)/(1+$ItemDiscount2/100)
        } else {
            //do nothing - do not include the line in calculation 
        }
        
        

    }

    // in case $totalInvoiceSum is not numeric  it change to 0 (zero) and echo 
    if (!is_numeric($totalInvoiceSum)) {
        echo "Total Invoice Sum from OCR is $totalInvoiceSum and not numeric so it have been changed to 0 <br>";
        $totalInvoiceSum = 0;

    } 

    //if all items numeric  - compare the total vs the calculated and echo accordingly 
    if ($AreItemsNumeric) {
        echo "Invoice sum is: $totalInvoiceSum <br>";
        echo "Calculated sum is: $CalculatedSum <br>";
        if ($CalculatedSum > ($totalInvoiceSum + 5) or  $CalculatedSum < ($totalInvoiceSum - 5)) { 
            echo "Difference is higher than 5 - NOT OK <br>";
        } else {
            echo "Difference is lower than 5 - OK <br>";
    }
    } else{
        //if there is atlease one non-numeric value then declaire the calculation can not be done
        echo "Not all items in OCRsanity sheet are numeric - THE PROCESS CAN NOT BE COMPLETED <br>";
    }

    //return a pair of Invoice sum [0] and calculated Sum [1]
    return [$totalInvoiceSum,$CalculatedSum];
}

//Do the CheckSum based on Qty*price for each line.
//if invocie does not match calculation then each line needs to examine in seperate 
//The flat discount part will be implemented later 
function checkSumOCRsanityNoLineTotal($OCRsanitySpreadSheet,$shopData) {

    //get the total sum of the invoice
    $OCRsanitySheet = $OCRsanitySpreadSheet->getActiveSheet();
    $totalInvoiceSum = $OCRsanitySheet->getCell([2,4])->getValue();
    $supplierName = $OCRsanitySheet->getCell([2,2])->getValue();

    //iterate through the Quantaty and Price and accumulate the sum
    $CalculatedSum = 0;

    //set the indication if all numeric items are actually numeric
    $AreAllItemsNumeric = true;

    //get the last row 
    $lastUsedRow = $OCRsanitySheet->getHighestRow('A');

    //get the supplier flat discount data
    //$flatDiscountArray = getFlatDiscountSupplier($shopData,$supplierName);
    //if the info is null then it menas the shops.json and suppleir.json are not sync - 
    //you need to add the "flatDiscountSuppliers" value to the shops.json for this shop
    /*if ($flatDiscountArray == null) {
        //echo as the suppilers.json is not inline with the shops.json
        echo "The supplier $supplierName does not have flat discount value and supervised products at suppliers.json file <br>";
        return;
    } 
        */

    //get the flat discount value from the supplier lfat discount object 
    //$flatDiscount = $flatDiscountArray[0];

    //$productsUnderSupervisioArray = array_slice($flatDiscountArray,1);

    
    for ($rowIndex = 7; $rowIndex <= $lastUsedRow; $rowIndex++) {

        //get the Price and Qty value
        $ItemPrice = $OCRsanitySheet->getCell([1,$rowIndex])->getvalue();
        $ItemQty = $OCRsanitySheet->getCell([2,$rowIndex])->getvalue();
        $ItemCatalogNo = $OCRsanitySheet->getCell([3,$rowIndex])->getvalue();

        //actual invocie row (for echo purposes)
        $actualInvoiceRow = $rowIndex-6;

        // If the price value is not numeric
        if (!is_numeric($ItemPrice)) {
            //echo for stoping the process 
            echo "The Price value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreAllItemsNumeric = false;
        } 

        // If the Qty value is not numeric
        if (!is_numeric($ItemQty)) {
            //echo for stoping the process 
            echo "The Quantity value in invoice row $actualInvoiceRow is non-numeric.PLEASE CORRECT <br>";
            //Pain the cell in purple
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
            $OCRsanitySheet->getStyle([2,$rowIndex])->getFill()->getStartColor()->setARGB('80DCC8FF');
            //indicate that not all items are numeric
            $AreAllItemsNumeric = false;
        }


        //check if the $ItemCatalogNo is under supervision 
        $isUnderSupervision = false;
        /*
        foreach ($productsUnderSupervisioArray as $productCatalogNo) {
            if ($productCatalogNo == $ItemCatalogNo) {
                $isUnderSupervision = true;
                echo "Product with catalog No $ItemCatalogNo is under supervision <br>";
                break;
            }
        }
            */

        


        //if values in the row are numerica - add up the price*Qty of each line
        if (is_numeric($ItemQty) and is_numeric($ItemQty)) {
            //if the item is under supervision - keep the price and paint the cell in green
            if ($isUnderSupervision == true) { //!!!!!!!!!!!!!!!! CHNAGE TO ALWAYS $isUnderSupervision == false !!!!!!!!!!!!
                //product under supervision 
                //echo "Producst in row $actualInvoiceRow is under price suprvision<br>";
                $CalculatedSum = $CalculatedSum + $ItemPrice*$ItemQty; //price stay 
                //paint the cell in green 
                $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->setFillType(Fill::FILL_SOLID);
                $OCRsanitySheet->getStyle([1,$rowIndex])->getFill()->getStartColor()->setARGB('8020FF20');
            } else {
                //if not undersupervision
                $ItemPrice = $ItemPrice;
                $CalculatedSum = $CalculatedSum + $ItemPrice*$ItemQty;
            }
            

        } else {
            //do nothing - do not include the line in calculation 
        }


    }

    // in case $totalInvoiceSum is not numeric  it change to 0 (zero) and echo 
    if (!is_numeric($totalInvoiceSum)) {
        echo "Total Invoice Sum from OCR is $totalInvoiceSum and not numeric so it have been changed to 0 <br>";
        $totalInvoiceSum = 0;

    } 

    //if all items numeric  - compare the total vs the calculated and echo accordingly 
    if ($AreAllItemsNumeric) {
        echo "Invoice sum is: $totalInvoiceSum <br>";
        echo "Calculated sum is: $CalculatedSum <br>";
        if ($CalculatedSum > ($totalInvoiceSum + 5) or  $CalculatedSum < ($totalInvoiceSum - 5)) { 
            echo "Difference is higher than 5 - NOT OK <br>";
        } else {
            echo "Difference is lower than 5 - OK <br>";
    }
    } else{
        //if there is atlease one non-numeric value then declaire the calculation can not be done
        echo "Not all items in OCRsanity sheet are numeric - THE PROCESS CAN NOT BE COMPLETED <br>";
    }

    //return a pair of Invoice sum [0] and calculated Sum [1]
    return [$totalInvoiceSum,$CalculatedSum];
    
}

?>