<?php
include 'functions/configFunctions.php';

//takes the input to enable excel format from backoffice and convert to 
function ConvertSalesToInnoFormat($shop, $supplier) {
    // Display the file upload form
    echo "<h1>Please upload the Daily Sales File</h1>";
    echo "<form action='convertSalesToInnoFormat.php' method='post' enctype='multipart/form-data'>";
    echo "<input type='file' name='SalesTable' required><br>";
    echo "<h1>Please upload the Products list file </h1>";
    echo "<input type='file' name='ProductsTable' required><br>";
    // Include hidden input fields for $shop and $supplier
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='submit' value='Upload Files'>";
    echo "</form>";
}

function PreProcessFiles($shop, $supplier) {

    //upload the shop form and get the 
    $shopsjsonData = uploadJSONdata('shops.json');
    $shopData = getShopForm($shopsjsonData, $shop);
    $preProcessMethod = $shopData['preProcessMethod'];

    // Determine the correct target file based on the shop
    if ($preProcessMethod === "SinglePage") {
        $action = 'preProcess/activatePreProcess.php';
    } elseif ($preProcessMethod === "MultiPage") {
        $action = 'preProcess/ProcessMultiPageInvoice.php';
    } else {
        // Terminate the process with an error message
        die("❌ <strong>PreProcessMethod</strong> parameter is not properly defined in the <code>shop.json</code> config file – process terminated.");
    }

    // Display the hidden form
    echo "<form id='hiddenForm' action='" . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . "' method='post'>";
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    echo "</form>";

    // Auto-submit the form using JavaScript
    echo "<script type='text/javascript'>
        document.getElementById('hiddenForm').submit();
    </script>";
}

/*
function PreProcessFiles($shop, $supplier) {
    // Display the hidden form
    echo "<form id='hiddenForm' action='preProcess/activatePreProcess.php' method='post'>";
    // Include hidden input fields for $shop and $supplier
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    echo "</form>";

    // Add JavaScript to automatically submit the form
    echo "<script type='text/javascript'>
        document.getElementById('hiddenForm').submit();
    </script>";
}

*/

function EditOCRsanity($shop, $supplier) {
    // Display the hidden form
    echo "<form id='hiddenForm' action='tableEdit/process_editTable.php' method='post'>";
    // Include hidden input fields for $shop and $supplier
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    echo "</form>";

    // Add JavaScript to automatically submit the form
    echo "<script type='text/javascript'>
        document.getElementById('hiddenForm').submit();
    </script>";
}

function DownloadOCRFiles($shop, $supplier) {
    // Display the hidden form
    echo "<form id='hiddenForm' action='gmailAccess/download_ocrfiles_from_emails.php' method='post'>";
    // Include hidden input fields for $shop and $supplier
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";
    echo "</form>";

    // Add JavaScript to automatically submit the form
    echo "<script type='text/javascript'>
        document.getElementById('hiddenForm').submit();
    </script>";
}

function PackFilesForBackOffice($shop, $supplier) {
    // Display the form title
    echo "<h1>Please choose a date for back office files pack</h1>";
    echo "<form action='BOpack/processBOpack.php' method='post' enctype='multipart/form-data'>";

    // Default value for Process Date
    $today = date('Y-m-d'); // Get today's date in the format 'YYYY-MM-DD'

    // Input field for Process Date
    echo "Process Date: <input type='date' name='processDate' value='$today' required><br><br>";

    // Hidden fields for shop and supplier values of POST
    echo "<input type='hidden' name='shop' value='" . htmlspecialchars($shop, ENT_QUOTES, 'UTF-8') . "'>";
    echo "<input type='hidden' name='supplier' value='" . htmlspecialchars($supplier, ENT_QUOTES, 'UTF-8') . "'>";

    // Submit button
    echo "<input type='submit' value='Submit'>";
    echo "</form>";
}

// Define the EchoFunc function
function EchoFunc($shop, $supplier) {
    // Create a string with the shop and supplier names
    $result = "Shop: $shop<br>Supplier: $supplier <br>Function: EchoFunc";
    echo $result;
}

//definde the dividTotalWithQty func
function dividTotalWithQty($shop, $supplier) {
    // Create a string with the shop and supplier names
    $result = "Shop: $shop<br>Supplier: $supplier <br>Function: dividTotalWithQty";
    echo $result;
}

?>
