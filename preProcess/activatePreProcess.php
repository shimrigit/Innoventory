<?php
require '../vendor/autoload.php'; // Ensure MPDF is autoloaded
include '../functions/configFunctions.php';

use Mpdf\Mpdf;

session_start(); // Start session

// Unset the session variable from prevoius sessions 
unset($_SESSION['groups']);

//get the sho confige file 
$shop = isset($_POST['shop']) ? $_POST['shop'] : null;
$supplier = isset($_POST['supplier']) ? $_POST['supplier'] : null;

//store the $shop and $supplier data in the session 
$_SESSION['shop'] = $shop;
$_SESSION['supplier'] = $supplier;

//upload shop data and supplier data 
$shopsjsonData = uploadJSONdata('../shops.json');
$shopData = getShopForm($shopsjsonData,$shop);

$directory = $shopData["preProcessFilesDirectory"];
$logFileName = $shopData["preProcessLogfileName"];
$logFile = $directory . $logFileName;

$_SESSION['preProcessDirectory'] = $directory;
$_SESSION['logFile'] = $logFile;

//set parameters for emails to send to OCR machine 
$_SESSION['preProcessFROMemailAddress'] = $shopData["preProcessFROMemailAddress"];
$_SESSION['preProcessDestinationEmailAddress'] = $shopData["preProcessDestinationEmailAddress"];
$_SESSION['preProcessToSend'] = $shopData["preProcessToSend"];


// Load suppliers JSON
//$suppliersJson = uploadJSONdata('../suppliers.json');
//$suppliersJson = '[{"supplierName":"Tnuva","OCRsanityMethod":"LineTotal","hebrewName":"תנובה"},{"supplierName":"StraussCool","OCRsanityMethod":"LineTotal","hebrewName":"שטראוס מצוננים"},{"supplierName":"Caspin","OCRsanityMethod":"LineTotal","hebrewName":"כספין"},{"supplierName":"Dubek","OCRsanityMethod":"LineTotal","hebrewName":"דובק"}]';
//$suppliers = json_decode($suppliersJson, true);
$suppliers = uploadJSONdata('../suppliers.json');

// Initialize log file and final message string
$finalMessage = "Log started at " . date('Y-m-d H:i:s') . PHP_EOL;
file_put_contents($logFile, $finalMessage, FILE_APPEND);

// Get JPG files in the directory
$files = glob($directory . '\\*.jpg'); // $files = glob($directory . '\\*.jpg');

// Convert file paths to **relative** URLs and keep absolute paths for unlink
$fileData = array_map(function($file) {
    $relativeUrl = str_replace('C:/xampp/htdocs', '', str_replace('\\', '/', $file));  // URL for browser
    return [
        'relativeUrl' => $relativeUrl,  // Relative URL for the browser
        'absolutePath' => $file         // Absolute path for unlink()
    ];
}, $files);

// Debug: Print the URLs for testing
/*echo "<h3>Debug: Generated Relative URLs and Absolute Paths</h3>";
foreach ($fileData as $file) {
    echo "<p>Relative URL: <a href='{$file['relativeUrl']}' target='_blank'>{$file['relativeUrl']}</a></p>";
    echo "<p>Absolute Path: {$file['absolutePath']}</p>";
} */

// Check if files exist in the directory
if ($files === false || count($files) == 0) {
    $finalMessage .= "No files were found at " . date('Y-m-d H:i:s') . PHP_EOL;
    echo "<p>No files were found.</p>";
    file_put_contents($logFile, "No files were found at " . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
} else {
    // Send the list of file URLs and absolute paths to JavaScript
    echo "<script>
        let files = " . json_encode($fileData) . ";
        let suppliers = " . json_encode($suppliers) . ";
    </script>";

    // Output hidden form (processed via JavaScript)
    echo "<div id='fileForm'>
        <p id='fileDisplay'></p>
        <form id='processForm' method='post' action='process_file.php'>
            <select name='supplierName' id='supplierSelect' required></select>
            <input type='date' name='selectedDate' id='datePicker' value='" . date('Y-m-d') . "' required>
            <input type='hidden' name='relativeUrl' id='fileRelativeUrl'>
            <input type='hidden' name='absolutePath' id='fileAbsolutePath'>
            <button type='button' id='saveBtn'>Save and Continue</button>
            <button type='button' id='discardBtn'>Discard</button>
        </form>
        <img id='imagePreview' src='' style='max-width: 75%; max-height: 112.5vh; height: auto;' alt='Image preview'><br>
    </div>";

    // Output the summary area for logs
    echo "<div id='summary'></div>";

    // JavaScript handles the loop and form submission
    echo "<script>
        let currentIndex = 0;

        // Populate the supplier dropdown
        const supplierSelect = document.getElementById('supplierSelect');
        suppliers.forEach(supplier => {
            const option = document.createElement('option');
            option.value = supplier.supplierName;
            option.textContent = supplier.hebrewName;
            supplierSelect.appendChild(option);
        });

        // Function to load the current file
        function loadFile() {
            if (currentIndex < files.length) {
                let file = files[currentIndex];
                document.getElementById('fileDisplay').innerHTML = 'Processing File: ' + file.relativeUrl;
                document.getElementById('fileRelativeUrl').value = file.relativeUrl;
                document.getElementById('fileAbsolutePath').value = file.absolutePath;

                // Set the image preview for the current file (Relative URL)
                document.getElementById('imagePreview').src = file.relativeUrl;
            } else {
                // Append the final message to the summary without removing previous logs
                document.getElementById('summary').innerHTML += '<h3>All files processed. Process terminated.</h3>';
                document.getElementById('fileForm').style.display = 'none';

                // Open newPage.php in a new window or tab
                setTimeout(function() {
                    window.open('append_pages.php', '_blank'); // Open in a new window/tab
                }, 1000);  // Optional delay of 1 second
            }
        }

        // Event listener for 'Save and Continue' button
        document.getElementById('saveBtn').addEventListener('click', function() {
            processFile('save');
        });

        // Event listener for 'Discard' button
        document.getElementById('discardBtn').addEventListener('click', function() {
            processFile('discard');
        });

        // Function to process the file via an AJAX request
        function processFile(action) {
            const formData = new FormData(document.getElementById('processForm'));
            formData.append('action', action);

            // Send AJAX request to process the file
            fetch('process_file.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text())
            .then(data => {
                document.getElementById('summary').innerHTML += data; // Append log to summary
                currentIndex++; // Move to the next file
                loadFile(); // Load the next file
            });
        }

        // Load the first file when the page loads
        loadFile();
    </script>";
}
?>