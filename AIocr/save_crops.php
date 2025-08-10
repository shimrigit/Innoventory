<?php
$saveDir = "C:/xampp/htdocs/website/AIocr/crops"; 

if (!isset($_POST['imagesJson']) || !isset($_POST['originalFileName'])) {
    die("Missing data.");
}

$images = json_decode($_POST['imagesJson'], true);
$baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $_POST['originalFileName']);

// Track label counts (for Table1, Table2, etc.)
$labelCounts = [];

foreach ($images as $item) {
    $label = preg_replace('/[^a-zA-Z0-9]/', '_', $item['label']);
    $imgData = $item['imageData'];

    // Add numeric suffix if duplicate label (e.g., Table1, Table2)
    if (!isset($labelCounts[$label])) {
        $labelCounts[$label] = 1;
    } else {
        $labelCounts[$label]++;
    }

    $labelSuffix = $label;
    if ($labelCounts[$label] > 1) {
        $labelSuffix .= $labelCounts[$label];
    }

    $filename = "{$baseName}_{$labelSuffix}.png";
    $filepath = $saveDir . DIRECTORY_SEPARATOR . $filename;

    // If file already exists, append "_New", "_New2", etc.
    $n = 1;
    while (file_exists($filepath)) {
        $filename = "{$baseName}_{$labelSuffix}_New" . ($n > 1 ? $n : '') . ".png";
        $filepath = $saveDir . DIRECTORY_SEPARATOR . $filename;
        $n++;
    }

    // Extract base64 and save
    if (preg_match('/^data:image\/png;base64,/', $imgData)) {
        $imgData = substr($imgData, strpos($imgData, ',') + 1);
        $imgData = base64_decode($imgData);
        file_put_contents($filepath, $imgData);
    }
}

echo "Images saved successfully.";
?>
