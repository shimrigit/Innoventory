<?php

//append_pages.php

session_start(); // Start session to track groups

$dir = $_SESSION['preProcessDirectory'];
$logFile = $_SESSION['logFile'];

// Directory to scan for PDF files
//$dir = 'C:/xampp/htdocs/website/preProcessDir';
// Set directory and log file
//$directory = 'C:\\xampp\\htdocs\\website\\preProcessDir';
//$logFile = $dir . '\\filelog.txt';

// Scan directory for PDF files
$pdfFiles = glob($dir . '/*.pdf');


// Function to group files based on the base name (supplier and date)
function group_files_by_base_name($files) {
    $groups = [];

    foreach ($files as $file) {
        $baseName = preg_replace('/\s\d+$/', '', basename($file, '.pdf')); // Remove sequence numbers
        $groups[$baseName][] = $file;
    }

    // Filter out groups that only have one file (i.e., not a group)
    return array_filter($groups, function($group) {
        return count($group) > 1; // Only include groups with more than one file
    });
}

// If this is the first time loading, store the groups in the session
if (!isset($_SESSION['groups'])) {
    $logMessage = "Start session of files appending ";
    echo $logMessage."<br>";  
    file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);  
    $_SESSION['groups'] = group_files_by_base_name($pdfFiles);
}

// Get the current group of files
$groups = $_SESSION['groups'];

/*
// If there are no more groups to process, finish
if (empty($groups)) {
    echo "All groups have been processed.";
    session_destroy(); // Clear session
    exit;
} */

// If there are no more groups to process, finish
if (empty($_SESSION['groups'])) {
    echo "All groups have been processed.";
    //session_destroy(); // Clear session

    // Output JavaScript to open the new window
    echo "<script type='text/javascript'>
            window.open('../gmailAccess/sendToOCR.php', '_blank');
          </script>";
    
    // You can redirect the current page to some other page or just stop further execution
    exit; // Stop further execution
}

// Display any error from the previous submission
if (isset($_SESSION['error'])) {
    echo "<p style='color: red;'>{$_SESSION['error']}</p>";
    unset($_SESSION['error']); // Clear the error after displaying it
}

// Display the first group
$group = reset($groups);  // Get the first group
$groupBaseName = key($groups);  // Get the base name of the group
//log message for the group
$logMessage = "Start appending group for $groupBaseName. ";
echo $logMessage."<br>";  
file_put_contents($logFile, $logMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND); 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Append Pages for Group: <?php echo $groupBaseName; ?></title>
</head>
<body>
    <h1>Append Pages for Group: <?php echo $groupBaseName; ?></h1>

    <form method="post" action="process_append.php">
        <?php foreach ($group as $index => $file): ?>
            <div>
                <p>File Name: <?php echo basename($file, '.pdf'); ?></p>

                <!-- Page number dropdown -->
                <label for="pageNumber<?php echo $index; ?>">Page Number:</label>
                <select name="pageNumber[]" id="pageNumber<?php echo $index; ?>">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>

                <!-- Sub-group dropdown -->
                <label for="subGroup<?php echo $index; ?>">Sub-group:</label>
                <select name="subGroup[]" id="subGroup<?php echo $index; ?>">
                    <?php foreach (range('A', 'J') as $letter): ?>
                        <option value="<?php echo $letter; ?>"><?php echo $letter; ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Hidden input to pass file name -->
                <input type="hidden" name="file[]" value="<?php echo $file; ?>">

                <!-- Embed the PDF below the controls -->
                <embed src="<?php echo str_replace('C:/xampp/htdocs', '', $file); ?>" type="application/pdf" width="600" height="400">
            </div>
            <hr>
        <?php endforeach; ?>

        <button type="submit">Submit</button>
    </form>
</body>
</html>
