
<?php

use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

//get JSON file and return the JSON object 
function uploadJSONdata($jsonFilePath) {

    $configContent = file_get_contents($jsonFilePath);
    $configData = json_decode($configContent, true);

    if (!$configData) {
        die('Failed to decode config file.');
    }

    return $configData;

}


function getShopForm($jsonData,$shopName) {

    // Find the form in the config data
    $formConfig = null;
    foreach ($jsonData['forms'] as $form) {
        if ($form['name'] === $shopName) {
            $formConfig = $form;
            break;
        }
    }

    if (!$formConfig) {
        die("Form '$shopName' not found in the config file.");
    }

    return $formConfig;

}


function getSupplierData($supplierJsonData, $supplierName) {

    $supplierConfig = null;
    foreach ($supplierJsonData as $object) {
        if ($object["supplierName"] === $supplierName) {
            $supplierConfig = $object;
            break;
        }
    }

    if (!$supplierConfig) {
        die("The supplier '$supplierName' was not found in the config file.");
    }

    return $supplierConfig;

}

//get the array that represent the flat discount 
function getFlatDiscountSupplier($shopData,$supplierName) {

    //get the object array 
    $allSuppliers = $shopData['flatDiscountSuppliers'];

    //echo "allSuppliers ".json_encode($allSuppliers);

    $supplierObject = null;

    //itterate through the suppliers to get the supplier array
    foreach ($allSuppliers as $item) {
        if (isset($item['Berman'])) {
            $supplierObject = $item['Berman'];
            break; // Exit loop once found
        }
    }

   // echo "supplierObject: ".json_encode($supplierObject)." <br>";

    return $supplierObject; 

}

function getSupplierPriceParam($shopData,$supplierName) {

    //get the object array 
    $allSuppliers = $shopData['supplierParam'];

    $supplierObject = null;

    //itterate through the suppliers to get the supplier array
    foreach ($allSuppliers as $item) {
        if (isset($item[$supplierName])) {
            $supplierObject = $item[$supplierName];
            break; // Exit loop once found
        }
    }
    
    //set the diffult value - 0 (regular)
    $supplierPriceParam = 0;

   //get the PriceParam from the supplier values 
    if (isset($supplierObject)) {
        $supplierPriceParam = $supplierObject[2]; // set the parm - 0 or 1 as in the config file 
        echo "Supplier Price Param : $supplierPriceParam <br>";
    } else {
        echo "$supplierName Price param (0-retular, 1 - LineTotal/Qty was not found  - defult values set to 0<br>";
    }

    return $supplierPriceParam;

}

function saveSpreadsheetWithTimestamp($spreadsheet, $filePath,$rightToLeft,$suffix) {
    // Get the directory and file name from the original file path
    $directory = pathinfo($filePath, PATHINFO_DIRNAME);
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);

    // Generate a timestamp for the XLSX file suffix
    $timestamp = date("dmy_His");

    // Create the new file name with the timestamp suffix
    $newFileName = $fileName . $suffix."_$timestamp.xlsx";

    

    // Set the right-to-left layout for the sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft($rightToLeft);

    // Save the modified Excel object using the Xlsx writer
    $writer = new Xlsx($spreadsheet);
    $writer->save("$directory/$newFileName");

    //return the location od the file
    return "$directory/$newFileName";

}

function saveSpreadsheet($spreadsheet, $filePath,$rightToLeft,$suffix) {
    // Get the directory and file name from the original file path
    $directory = pathinfo($filePath, PATHINFO_DIRNAME);
    $fileName = pathinfo($filePath, PATHINFO_FILENAME);

    // Generate a timestamp for the XLSX file suffix
    //$timestamp = date("dmy_His");

    // Create the new file name with the timestamp suffix
    $newFileName = $fileName . $suffix.".xlsx";

    

    // Set the right-to-left layout for the sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft($rightToLeft);

    // Save the modified Excel object using the Xlsx writer
    $writer = new Xlsx($spreadsheet);
    $writer->save("$directory/$newFileName");

    //return the location od the file
    return "$directory/$newFileName";

}

function saveSpreadsheetToXLSXWithTimestamp($spreadsheet, $fullPath,$fileName,$rightToLeft) {

    // Generate a timestamp for the XLSX file suffix
    $timestamp = date("dmy_His");

    // Create the new file name with the timestamp suffix
    $fullFileName = $fullPath."/".$fileName."_$timestamp.xlsx";

    

    // Set the right-to-left layout for the sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft($rightToLeft);

    // Save the modified Excel object using the Xlsx writer
    $writer = new Xlsx($spreadsheet);
    $writer->save($fullFileName);

    //return the location od the file
    return $fullFileName;

}

//The functions go to the directory of the PDF and JSON files from the OCR
//and get the name including the relative path of the oldest pair 
//The name is a "Base name" without the PDF or JSON suffix 
function getOldestPairName(string $directoryPath) { //The input is the relative directory to the .php file

    //$directory = 'C:/xampp/htdocs/website/ocrDir';
    //echo "Directory path is $directoryPath <br>";

    // Get all JSON and PDF files in the directory
    //$jsonFiles = glob($directoryPath . '/*.[Jj][Ss][Oo][Nn]');
    $jsonFiles = glob($directoryPath . '/*.JSON'); 
    //$pdfFiles = glob($directory . '/*.pdf');

    //if not .JSON files found - terminate the process 
    if (empty($jsonFiles)) {
        die("No JSON files found with .JSON extension. <br>Verify .JSON (uppercase) are in ocrDir directory and try again <br>Process terminated");
    }

    // Create an array to store pairs of JSON and PDF files with matching names
    $pairs = [];

    foreach ($jsonFiles as $jsonFile) {
        $baseName = basename($jsonFile, '.JSON');
        $pdfFile = $directoryPath . '/' . $baseName . '.pdf'; 
        if (file_exists($pdfFile)) {
            $pairs[$baseName] = [
                'JSON' => $jsonFile,
                'pdf' => $pdfFile,
                'mtime' => filemtime($jsonFile)
            ];
        }
    }
    

    // Check if we found any pairs
    if (empty($pairs)) {
        die("No matching JSON and PDF pairs found in the directory. Process terminated"); 
    }

    // Find the oldest pair based on the modification time
    $oldestPair = array_reduce($pairs, function ($oldest, $pair) {
        return $oldest === null || $pair['mtime'] < $oldest['mtime'] ? $pair : $oldest;
    });

    $oldestPairBaseName = basename($oldestPair['JSON'], '.JSON');

    // Output the name of the oldest pair (without extension)
    //echo "The oldest pair is: " . $oldestPairBaseName . "<br>";
    //echo "JSON File: " . $oldestPair['JSON'] . "<br>";
    //echo "PDF File: " . $oldestPair['pdf'] . "<br>";

    return $oldestPairBaseName;

}

function generateNextSuffix($filePath) {

    // Check if the file name ends with 6 digits followed by .xlsx
    if (preg_match('/_(\d{6})\.xlsx$/', $filePath, $matches)) {
        $suffix = '_RV1';
    } 
    // Check if the file name ends with _RV followed by a number and .xlsx
    elseif (preg_match('/_RV(\d+)\.xlsx$/', $filePath, $matches)) {
        $version = (int)$matches[1] + 1;
        if ($version > 999) {
            // If the new version exceeds 999, handle accordingly
            // For example, you could return an error or handle differently
            return "Error: Version exceeds limit.";
        }
        $suffix = '_RV' . $version;
    } else {
        // If the file name does not match any of the expected patterns, return an empty suffix or an error
        $suffix = 'ERROR';
    }

    return $suffix;
}

function replaceSuffix_RV($filePath) {
    $newSuffix = generateNextSuffix($filePath);

    //file_put_contents('debug.txt', "Inside replaceSuffix_RV - New Suffix: $newSuffix\n", FILE_APPEND);

    if ($newSuffix === "Error: Version exceeds limit.") {
        return $newSuffix;
    }

    // Check if the file name ends with 6 digits followed by .xlsx
    if (preg_match('/_(\d{6})\.xlsx$/', $filePath)) {
        $newFilePath = preg_replace('/_(\d{6})\.xlsx$/', '_\1' . $newSuffix . '.xlsx', $filePath);
    } 
    // Check if the file name ends with _RV followed by a number and .xlsx
    elseif (preg_match('/_RV(\d+)\.xlsx$/', $filePath)) {
        $newFilePath = preg_replace('/_RV(\d+)\.xlsx$/', $newSuffix . '.xlsx', $filePath);
    } else {
        // If the file name does not match any of the expected patterns, return the original file path
        $newFilePath = $filePath;
    }

    //file_put_contents('debug.txt', "New File Path: $newFilePath\n", FILE_APPEND);

    return $newFilePath;
}

function restoreInvoiceNamePdf($filePath) {
    // Extract the file name from the path
    $fileName = basename($filePath);
    
    // Remove the _OCRsanity_xxxxxx_xxxxxx_RVy suffix and replace with .pdf extension
    $newFileName = preg_replace('/_OCRsanity_\d{6}_\d{6}_RV\d+\.xlsx$/', '.pdf', $fileName);
    
    return $newFileName;
}

function getNewestXlsxFileName(string $directoryPath): ?string {
    // Ensure the directory path ends with a slash
    $directoryPath = rtrim($directoryPath, '/') . '/';

    // Get all .xlsx files in the directory
    $xlsxFiles = glob($directoryPath . '*.xlsx');

    // If no .xlsx files found, return null
    if (empty($xlsxFiles)) {
        echo "No xlsx files found in the directory <br>";
        return null;
    }

    // Initialize variables to track the newest file
    $newestFile = null;
    $newestFileTime = 0;

    // Iterate through each file and find the newest one
    foreach ($xlsxFiles as $file) {
        $fileCreationTime = filectime($file);
        if ($fileCreationTime > $newestFileTime) {
            $newestFileTime = $fileCreationTime;
            $newestFile = $file;
        }
    }

    // Return the basename of the newest file
    return $newestFile ? basename($newestFile) : null;
}

//ge the supplier name from the file path according to naming convention 
//the file name starts with the supplier name until the first underscore "_"
function getSupplierNameFromFilePath(string $filePath): ?string {
    // Extract the file name from the file path
    $fileName = basename($filePath);

    // Find the position of the first underscore in the file name
    $underscorePos = strpos($fileName, '_');

    // If there is no underscore, return null
    if ($underscorePos === false) {
        return null;
    }

    // Extract the supplier name from the start of the file name to the first underscore
    $supplierName = substr($fileName, 0, $underscorePos);

    return $supplierName;
}

function getInvoiceDateValFromFilePath($filePath) {
    // Get the basename of the file path
    $basename = basename($filePath);
    
    // Find the positions of the first and second underscores
    $firstUnderscorePos = strpos($basename, '_');
    $secondUnderscorePos = strpos($basename, '_', $firstUnderscorePos + 1);
    
    // Extract the date value between the first and second underscores
    if ($firstUnderscorePos !== false && $secondUnderscorePos !== false) {
        return substr($basename, $firstUnderscorePos + 1, $secondUnderscorePos - $firstUnderscorePos - 1);
    }
    
    // Return null if the date cannot be found
    echo "Date value was not found. Date set to defult date 01.01.1970 <br>";
    return "01.01.1970";
}

function invoiceFileNametoBOpdf($filePath) {
    // Extract the base name of the file
    $baseName = basename($filePath);
    
    // Find the position of "_OCRsanity_"
    $pos = strpos($baseName, '_OCRsanity_');
    
    // If "_OCRsanity_" is found in the base name
    if ($pos !== false) {
        // Extract the part before "_OCRsanity_"
        $newBaseName = substr($baseName, 0, $pos);
        
        // Add ".pdf" to the new base name
        $newBaseName .= '.pdf';
        
        return $newBaseName;
    } else {
        // If "_OCRsanity_" is not found, return the original base name with ".pdf"
        return $baseName . '.pdf';
    }
}

//set the columns datatype acording to the array logic 
function restoreSpreadSheetDataType(Spreadsheet $spreadSheet, array $dataTypeArray, string $filePath) {
    $sheet = $spreadSheet->getActiveSheet();
    $highestRow = $sheet->getHighestRow();

    foreach ($dataTypeArray as $pair) {
        $columnIndex = $pair[0];
        $dataType = $pair[1];

        for ($row = 2; $row <= $highestRow; $row++) {
            $cell = $sheet->getCell([$columnIndex, $row]);
            

            if ($dataType == 1) {
                // Set cell as text
                $cell->setDataType(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } elseif ($dataType == 2) {
                // Set cell as percentage
                $cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
            }

        }

        //autofit the column
        // Convert numeric column index to column letter
        $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
        // Auto-fit the column width
        $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
    }

    $writer = IOFactory::createWriter($spreadSheet, 'Xlsx');
    $writer->save($filePath);
}

function coordinateFromIndices($colIndex, $rowIndex) {
    $letters = '';
    while ($colIndex >= 0) {
        $letters = chr($colIndex % 26 + 65) . $letters;
        $colIndex = floor($colIndex / 26) - 1;
    }
    return $letters . ($rowIndex + 1); // Convert rowIndex to 1-based
}

function updateColumnWithSingleValue(Spreadsheet $Spreadsheet, int $columnNum, $value) {
    // Get the active worksheet from the spreadsheet
    $worksheet = $Spreadsheet->getActiveSheet();

    // Get the highest row number in the spreadsheet
    $highestRow = $worksheet->getHighestRow();

    // Iterate through the cells in the specified column
    for ($row = 2; $row <= $highestRow; $row++) {
        // Use the recommended way to set the cell value
        $worksheet->getCell([$columnNum, $row])->setValue($value);
    }
}

    
?>