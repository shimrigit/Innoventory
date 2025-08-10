<?php
if (isset($_POST['imageData']) && isset($_POST['originalPdfName'])) {
    $data = $_POST['imageData'];
    $pdfName = basename($_POST['originalPdfName']);
    $directory = "C:\\xampp\\htdocs\\website\\preProcessDir"; //dirname(__FILE__); // current directory

    // Extract the base name without extension
    $baseName = pathinfo($pdfName, PATHINFO_FILENAME);
    $outputPath = $directory . DIRECTORY_SEPARATOR . $baseName . "_crop.png";

    // Decode base64 image data
    $data = str_replace('data:image/png;base64,', '', $data);
    $data = base64_decode($data);

    file_put_contents($outputPath, $data);

    echo "✅ Image saved as: " . $baseName . "_crop.png";
} else {
    echo "❌ Invalid request";
}
?>
