<?php
session_start();
require_once '../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

$preProcessDir = realpath(__DIR__ . '/../preProcessDir');
$tempDir = $preProcessDir . '/TempPP';
$total = $_POST['totalFiles'] ?? 0;

if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Load suppliers.json to map Hebrew names to English
$suppliersMap = [];
$jsonPath = realpath(__DIR__ . '/../suppliers.json');
if (file_exists($jsonPath)) {
    $jsonData = json_decode(file_get_contents($jsonPath), true);
    foreach ($jsonData as $supplier) {
        $suppliersMap[$supplier['hebrewName']] = $supplier['supplierName'];
    }
}

$groups = [];
$discardedFiles = [];

for ($i = 1; $i <= $total; $i++) {
    $fileName = $_POST["fileName_$i"] ?? '';
    $supplierHebrew = trim($_POST["hebrewName_$i"] ?? '');
    $letter = trim($_POST["englishLetter_$i"] ?? '');
    $pageNumber = intval($_POST["pageNumber_$i"] ?? 0);
    $date = $_POST["date_$i"] ?? '';
    $discard = $_POST["discard_$i"] ?? '';
    $filePath = $preProcessDir . DIRECTORY_SEPARATOR . $fileName;

    // Always move discarded files
    if ($discard === 'yes') {
        if (file_exists($filePath)) {
            rename($filePath, $tempDir . DIRECTORY_SEPARATOR . basename($filePath));
        }
        $discardedFiles[] = $fileName;
        continue;
    }

    $supplierEnglish = $suppliersMap[$supplierHebrew] ?? $supplierHebrew; // fallback to Hebrew if mapping is missing
    $groupKey = "{$supplierEnglish}|{$letter}|{$date}";

    $groups[$groupKey][] = [
        'filePath' => $filePath,
        'pageNumber' => $pageNumber,
        'originalName' => $fileName
    ];
}

// Process each group
$summary = [];

foreach ($groups as $key => $pages) {
    usort($pages, fn($a, $b) => $a['pageNumber'] <=> $b['pageNumber']);

    [$supplierEnglish, $letter, $date] = explode('|', $key);
    $dateFormatted = date('d-m-y', strtotime($date));
    $outputName = "{$supplierEnglish} {$dateFormatted} {$letter}.pdf";
    $outputPath = $preProcessDir . DIRECTORY_SEPARATOR . $outputName;

    $pdf = new FPDI();

    foreach ($pages as $pageInfo) {
        $pdf->setSourceFile($pageInfo['filePath']);
        $tpl = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        // Move the original file
        $destPath = $tempDir . DIRECTORY_SEPARATOR . basename($pageInfo['filePath']);
        rename($pageInfo['filePath'], $destPath);
    }

    $pdf->Output($outputPath, 'F');

    $summary[] = [
        'name' => $outputName,
        'count' => count($pages),
        'pages' => array_column($pages, 'pageNumber')
    ];
}

// Output summary
echo "<h2>Documents Created:</h2>";
foreach ($summary as $doc) {
    $pageLabel = $doc['count'] === 1 ? 'page' : 'pages';
    echo "Document \"{$doc['name']}\" with {$doc['count']} $pageLabel<br>";
}

// Optionally show discarded files
if (!empty($discardedFiles)) {
    echo "<h3>Discarded Pages Moved:</h3>";
    foreach ($discardedFiles as $discarded) {
        echo htmlspecialchars($discarded) . "<br>";
    }
}

// Set session variables for sendToOCR.php
$_SESSION['preProcessDirectory'] = $preProcessDir;
$_SESSION['logFile'] = __DIR__ . '/../filelog.txt'; // Or wherever you keep logs
$_SESSION['preProcessFROMemailAddress'] = 'inno.ocr@gmail.com';
$_SESSION['preProcessDestinationEmailAddress'] = 'innoventory.tech@soluma.si';
$_SESSION['preProcessToSend'] = 1; // 0 = skip, 1 = draft, 2 = send

// Redirect to sendToOCR.php after processing
echo "<script>
  // Open sendToOCR.php in a new tab or window
  window.open('../gmailAccess/sendToOCR.php', '_blank');
</script>";

?>
