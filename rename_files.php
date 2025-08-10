<?php

// Define source and destination directories
$sourceDir = "C:/xampp/htdocs/website/ocrDir";
$destDir = $sourceDir . "/Mod";

// Ensure the destination directory exists
if (!is_dir($destDir)) {
    mkdir($destDir, 0777, true);
}

// Iterate through all files in the source directory
$files = scandir($sourceDir);

foreach ($files as $file) {
    // Skip special entries
    if ($file === '.' || $file === '..') {
        continue;
    }

    $filePath = $sourceDir . "\\" . $file;

    // Skip directories
    if (is_dir($filePath)) {
        continue;
    }

    echo "Processing file: $file\n";

    // Check if the filename contains the pattern XX-11-24
    if (preg_match('/(\d{2})-11-24/', $file, $matches)) {
        // Replace XX with 26 in the filename
        $newFileName = preg_replace('/\d{2}-11-24/', '29-11-24', $file);
        $newFilePath = $destDir . "\\" . $newFileName;

        // Copy the file with the new name to the destination directory
        if (copy($filePath, $newFilePath)) {
            echo "Renamed and moved: $file -> $newFileName\n";
        } else {
            echo "Failed to move: $file\n";
        }
    } else {
        echo "Skipped (no match): $file\n";
    }
}

echo "All files processed.\n";

?>
